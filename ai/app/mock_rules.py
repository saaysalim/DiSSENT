"""How the MockModel "reads" a message: simple, explainable patterns.

This stands in for the language model until a real one is chosen. It returns *labels*
for what it found (for example "a phone number"), never the found text itself, so the
ReAct trace and the database never repeat personal data from the message.

Note for the evaluation: these patterns were written by the same person who wrote the
test set (ai/eval/test_messages.jsonl), so the mock's scores are optimistic.
"""
import re

# Role addresses of organisations (press@, info@ ...) are public contact points.
ROLE_MAILBOXES = r'(?:press|info|contact|office|newsroom|media|editor|editorial|hello|support)'
EMAIL = re.compile(r'\b([A-Za-z0-9._%+-]+)@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b')
PHONE = re.compile(r'(?<![\w/:.])\+?\d(?:[\s().-]*\d){7,14}(?![\w/:])')
IBAN = re.compile(r'\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]){10,30}\b')
CARD = re.compile(r'\b\d{4}(?:[\s-]?\d{4}){3}(?:[\s-]?\d{1,3})?\b')
WALLET = re.compile(r'\b(?:0x[0-9a-fA-F]{40}|bc1[0-9a-z]{20,60}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})\b')
ID_WORDS = re.compile(r'\b(passport|national id|id card|identity (?:card|number)|pps|tazkira|social security)\b[^.]{0,40}?\b[A-Z]?\d[\dA-Z-]{4,}', re.I)
BIRTH = re.compile(r'\b(date of birth|born on|birthday)\b', re.I)
PASSWORD = re.compile(r'\b(password|passcode|pin)\b\s*(?:is|:|=)?\s*\S{4,}|\b(?:same|old|usual) password\b|\bpassword as (?:last time|before)\b', re.I)
LOGIN = re.compile(r'\b(log ?in|login|username|user)\b[^.]{0,40}\bpassword\b', re.I)
PHISH = re.compile(r'https?://[^\s]*(login|verify|account|signin|secure|password)[^\s]*', re.I)
PAY_CODE = re.compile(r'\b(hawala|western union|moneygram|money transfer|pay(?:s)? out)\b[^.]{0,80}\bcode\b', re.I)
COORDS = re.compile(r'-?\d{1,2}\.\d{3,}\s*,\s*-?\d{1,3}\.\d{3,}')
ADDRESS = re.compile(r'\b\d{1,4}\s+(?:[A-Z][a-z]+\s+){1,3}(?:Street|St|Road|Rd|Avenue|Ave|Lane|Square|Boulevard)\b'
                     r'|\b(?:Street|Road|Avenue|Lane)\s+\d{1,4}\b|\b(?:apartment|flat|apt)\s+\d+', re.I)
SHELTER = re.compile(r'\b(safe ?house|shelter|hiding|hide out|hideout)\b', re.I)
CROSSING = re.compile(r'\b(cross(?:es|ing)? the border|checkpoint|border crossing)\b', re.I)
WHEN = re.compile(r'\b(tomorrow|tonight|today|\d{1,2}\s?(?:am|pm)|at \d{1,2}[:.]\d{2})\b', re.I)
SOURCE_WORD = re.compile(r'\b(source|informant|whistle-?blower|leak(?:ed|s)?|insider|my contact|the person who (?:filmed|recorded|took|sent))\b', re.I)
IDENTIFIER = re.compile(r'\b(?:called|named|is)\s+[A-Z][a-z]+'                          # "is called Serhii", "is Ahmad"
                        r'|\b[A-Z][a-z]+\s+[A-Z]\.(?=\s|,)'                             # "Maryam K."
                        r'|\b[A-Z][a-z]+\s+[A-Z][a-z]+(?=,|\s+(?:he|she|the|works))'    # "Ahmad Rahimi, he"
                        r'|\b(?:brother|sister|cousin|father|mother|son|daughter|neighbou?r|wife|husband)\b'
                        r'|\bthe (?:head|chief|director|only|deputy)\b'
                        r'|\bworks? (?:in|at|inside|for)\b')
RELATIVE_AT_POST = re.compile(r'\bmy (?:brother|sister|cousin|father|mother|uncle|aunt|son|daughter)\b[^.]{0,30}\bworks? (?:at|in|for) the\b', re.I)


def find_risks(text, has_attachment):
    """Returns a list of (risk_type, label). Labels describe, they never quote."""
    risks = []

    def add(kind, label):
        if (kind, label) not in risks:
            risks.append((kind, label))

    for m in EMAIL.finditer(text):
        if not re.fullmatch(ROLE_MAILBOXES, m.group(1), re.I):
            add('personal_data', 'a private email address')
    if ID_WORDS.search(text):
        add('personal_data', 'an ID or passport number')
    if BIRTH.search(text):
        add('personal_data', 'a date of birth')

    has_iban = bool(IBAN.search(text))
    has_card = bool(CARD.search(text))
    if has_iban:
        add('financial', 'a bank account number (IBAN)')
    if has_card:
        add('financial', 'a payment card number')
    if WALLET.search(text):
        add('financial', 'a crypto wallet address')
    if PAY_CODE.search(text):
        add('financial', 'a payment code for a money transfer')

    if not has_iban and not has_card:
        for m in PHONE.finditer(text):
            digits = re.sub(r'\D', '', m.group(0))
            if 8 <= len(digits) <= 15 and not ID_WORDS.search(text):
                add('personal_data', 'a phone number')
                break

    if PASSWORD.search(text) or LOGIN.search(text):
        add('security_risk', 'a password or login details')
    if PHISH.search(text) and 'dissent.ie' not in text.lower():
        add('security_risk', 'a link that asks people to log in elsewhere')

    if COORDS.search(text):
        add('location', 'GPS coordinates')
    if ADDRESS.search(text):
        add('location', 'an exact address')
    if SHELTER.search(text) and (ADDRESS.search(text) or COORDS.search(text) or re.search(r'\b(basement|floor|room)\b', text, re.I)):
        add('location', 'where people are sheltering or hiding')
    if CROSSING.search(text) and WHEN.search(text):
        add('location', 'the time and place of a border crossing')

    if SOURCE_WORD.search(text) and IDENTIFIER.search(text):
        add('source_identity', 'details that could identify a source')
    elif RELATIVE_AT_POST.search(text):
        add('source_identity', 'a relative at a named workplace who could be identified')

    if has_attachment:
        add('attachment', 'an attachment that cannot be checked automatically')

    return risks
