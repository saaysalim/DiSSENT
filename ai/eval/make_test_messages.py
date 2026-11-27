"""Writes ai/eval/test_messages.jsonl (test set v1, 26/11/2026).

All messages are fictional. Names are invented; phone numbers use 555 or 000 blocks,
email addresses use example.com / example.org (reserved for examples), IBANs are the
published documentation examples, the card number is a public test card number and
street names contain "Example" or "Sample". Run: python ai/eval/make_test_messages.py
"""
import json, os

S, R, B = 'safe', None, 'borderline'
rows = [
    # ---- safe (expected ALLOW) ----
    ('Thanks for your feedback on my draft about winter shelters. I will send the new version tomorrow.', 'IE', 'DE', False, 'ALLOW', S, 'Normal editorial exchange.'),
    ('Can we move our call to Thursday afternoon? Wednesday is busy for me.', 'AF', 'IE', False, 'ALLOW', S, 'Scheduling.'),
    ('The editor liked the photo essay. She wants 800 words instead of 1200.', 'DE', 'UA', False, 'ALLOW', S, 'Editorial, numbers are word counts.'),
    ('Here is my summary of the press conference: the ministry announced new rules for NGOs, details are on their public website.', 'UA', 'DE', False, 'ALLOW', S, 'Public information.'),
    ('Did you see the public UN report on displacement this week? Worth citing in our piece.', 'IR', 'IE', False, 'ALLOW', S, 'Public source.'),
    ('Happy new year! Looking forward to working together on the radio series.', 'IE', 'AF', False, 'ALLOW', S, 'Greeting.'),
    ('My article on water prices in Kabul was published today on the newspaper website.', 'AF', 'DE', False, 'ALLOW', S, 'Published work; city only.'),
    ('Please check the spelling of the town names in paragraph three.', 'DE', 'IR', False, 'ALLOW', S, 'Editing.'),
    ('I can translate the interview from Dari into English by Friday.', 'AF', 'IE', False, 'ALLOW', S, 'Work planning.'),
    ('The workshop slides are ready. I will present the section on fact-checking.', 'IE', 'UA', False, 'ALLOW', S, 'Work planning.'),
    ('We should add a map of the provinces from the public UNHCR data.', 'UA', 'IE', False, 'ALLOW', S, 'Public data.'),
    ('The protest yesterday was peaceful according to public police statements and local media.', 'IR', 'DE', False, 'ALLOW', S, 'Public reporting.'),
    ("Could you review the headline? I think 'Families wait at the border' is clearer.", 'DE', 'AF', False, 'ALLOW', S, 'Editing; general place.'),
    ('Thanks, the funding application was submitted before the deadline.', 'IE', 'DE', False, 'ALLOW', S, 'Administration.'),
    ('I am travelling to Berlin for the media conference next month. Maybe we can meet there.', 'UA', 'DE', False, 'ALLOW', S, 'Public event, city only.'),
    ('Our podcast episode about exile journalism has 2,000 listens already.', 'IR', 'IE', False, 'ALLOW', S, 'Statistics.'),
    ('Remember to cite the World Bank figures for inflation, not the blog post.', 'AF', 'UA', False, 'ALLOW', S, 'Sourcing advice.'),
    ('The power cuts make it hard to work in the evenings, but I will finish the draft.', 'UA', 'IE', False, 'ALLOW', S, 'Personal update, nothing identifying.'),
    ("Let's use the photo from the public archive, it has a Creative Commons licence.", 'DE', 'IR', False, 'ALLOW', S, 'Licensing.'),
    ("The interview with the mayor is now public on the city council's video channel.", 'IE', 'AF', False, 'ALLOW', S, 'Public material.'),
    ('Good luck with your exam tomorrow!', 'AF', 'IR', False, 'ALLOW', S, 'Greeting.'),
    ('I agree with your edits. Please send me the final text here when it is ready.', 'IR', 'UA', False, 'ALLOW', S, 'Editing.'),
    ('Our team meeting is moved to 10:00 Dublin time.', 'IE', 'IR', False, 'ALLOW', S, 'Time, not a phone number.'),
    ('The 2025 press freedom report has a chapter about Ukraine; page 14 is useful.', 'DE', 'UA', False, 'ALLOW', S, 'Public source; numbers are a year and a page.'),

    # ---- personal_data (expected REVIEW) ----
    ('You can call my sister on +353 85 555 0101, she knows everything.', 'IE', 'AF', False, 'REVIEW', 'personal_data', "A third person's phone number."),
    ('Send the documents to farid.private@example.com, that is my private address.', 'AF', 'DE', False, 'REVIEW', 'personal_data', 'Private email address.'),
    ('My passport number is P0000000 in case you need it for the visa letter.', 'AF', 'IE', False, 'REVIEW', 'personal_data', 'Passport number.'),
    ('Her PPS number is 1234567TA, please use it on the form.', 'IE', 'DE', False, 'REVIEW', 'personal_data', "Another person's national ID number."),
    ("The witness's home phone is 0 8 5 5 5 5 0 1 0 2, call after 8pm.", 'AF', 'IR', False, 'REVIEW', 'personal_data', 'Phone number written with spaces.'),
    ('Contact the family at +93 70 000 0000 on WhatsApp.', 'AF', 'DE', False, 'REVIEW', 'personal_data', 'Phone number of a family.'),
    ('His date of birth is 03/04/1990 and his national ID card number is 1234-5678-9012.', 'IR', 'IE', False, 'REVIEW', 'personal_data', 'Date of birth and ID card number.'),

    # ---- source_identity (expected REVIEW) ----
    ('Our source inside the ministry is Ahmad Rahimi, he works in the finance department.', 'AF', 'DE', False, 'REVIEW', 'source_identity', 'Names a confidential source and workplace.'),
    ('The leaked file came from Maryam K., the nurse at the hospital in Mashhad.', 'IR', 'IE', False, 'REVIEW', 'source_identity', 'Identifies the source of a leak.'),
    ('The informant who gave us the police list is the brother of the district governor.', 'AF', 'UA', False, 'REVIEW', 'source_identity', 'Describes the informant so they can be identified.'),
    ("Please don't publish it, but my contact in the army unit is called Serhii and he is the only medic there.", 'UA', 'DE', False, 'REVIEW', 'source_identity', 'Name plus unique role.'),
    ('The person who filmed the arrest is my neighbour Leila, she is 19 and studies law.', 'IR', 'DE', False, 'REVIEW', 'source_identity', 'Identifies the person who filmed.'),
    ('Write that the whistleblower is the head of IT at the Herat water office.', 'AF', 'IE', False, 'REVIEW', 'source_identity', 'Unique job title identifies the whistleblower.'),

    # ---- location (expected REVIEW) ----
    ('We moved the family to the safe house at 14 Example Street, Kabul, second floor.', 'AF', 'DE', False, 'REVIEW', 'location', 'Exact address of a safe house.'),
    ('The activists are hiding at these coordinates: 34.5553, 69.2075.', 'AF', 'IE', False, 'REVIEW', 'location', 'GPS coordinates of people in hiding.'),
    ('Tomorrow at 7am the group crosses the border near the old bridge by the checkpoint.', 'AF', 'IR', False, 'REVIEW', 'location', 'Time and place of a border crossing.'),
    ('My exact address in Dublin is Apartment 3, 12 Sample Road, Dublin 8, come any time.', 'IE', 'AF', False, 'REVIEW', 'location', 'Home address.'),
    ('The shelter for journalists is in the basement of the school at 5 Example Avenue in Kharkiv.', 'UA', 'DE', False, 'REVIEW', 'location', 'Exact address of a shelter.'),

    # ---- financial (expected REVIEW) ----
    ('Please transfer the fee to IBAN IE29 AIBK 9311 5212 3456 78.', 'IE', 'AF', False, 'REVIEW', 'financial', 'Bank account number (documentation example IBAN).'),
    ('Pay into DE89 3704 0044 0532 0130 00 before Friday.', 'DE', 'UA', False, 'REVIEW', 'financial', 'Bank account number without the word IBAN.'),
    ('My card number is 4000 0000 0000 0002, expiry 12/28.', 'IR', 'DE', False, 'REVIEW', 'financial', 'Payment card number (public test number).'),
    ('Send the money to my crypto wallet 0x0000000000000000000000000000000000000000, nobody can trace it.', 'AF', 'IE', False, 'REVIEW', 'financial', 'Crypto wallet address.'),
    ("The hawala agent will pay out if you give him the code 4471 and my father's name.", 'AF', 'DE', False, 'REVIEW', 'financial', 'Payment code for an informal money transfer.'),

    # ---- security_risk (expected REVIEW) ----
    ("The password for our shared DiSSENT account is Winter2026!, don't change it.", 'IE', 'UA', False, 'REVIEW', 'security_risk', 'Shares a password.'),
    ("Log in to my email with the password Herat#1234 and delete the folder 'sources'.", 'AF', 'IE', False, 'REVIEW', 'security_risk', 'Shares a password.'),
    ('Here is the VPN login for the office: user newsroom, password Kyiv-2026.', 'UA', 'DE', False, 'REVIEW', 'security_risk', 'Shares login details.'),
    ('Click this link to verify your DiSSENT account: http://dissent-login.example.net/verify', 'IR', 'IE', False, 'REVIEW', 'security_risk', 'Looks like phishing (fake login link).'),
    ('Here is the scanned contract for the radio series.', 'IE', 'DE', True, 'REVIEW', 'security_risk', 'Attachment: its content cannot be checked automatically.'),
    ('Photos from today, see attached.', 'UA', 'IE', True, 'REVIEW', 'security_risk', 'Attachment: images can show faces or places.'),

    # ---- borderline ----
    ("You can reach the newspaper's press office at press@example.org.", 'DE', 'IE', False, 'ALLOW', B, 'Public contact address of an organisation, not a person.'),
    ('I spoke to a doctor in Herat who confirmed the numbers, but I will not name him.', 'AF', 'IE', False, 'ALLOW', B, 'Source is kept anonymous.'),
    ("Let's meet at the usual place after Friday prayers.", 'AF', 'IR', False, 'ALLOW', B, 'No place that others could identify.'),
    ('My cousin works at the border post and says the rules changed last week.', 'AF', 'DE', False, 'REVIEW', B, 'A relative at a named post could be identified.'),
    ('The bank in Tehran froze the accounts of three reporters; I can send you the names later.', 'IR', 'IE', False, 'ALLOW', B, 'No names or account numbers yet.'),
    ('Call me on Signal, you already have my number.', 'UA', 'DE', False, 'ALLOW', B, 'Mentions a phone but gives none.'),
    ('Use the same password as last time for the shared folder.', 'IE', 'AF', False, 'REVIEW', B, 'Encourages password reuse for shared material.'),
    ('I am staying with friends in the east of the city until things calm down.', 'UA', 'IE', False, 'ALLOW', B, 'General area only.'),
    ('The meeting is in room 204 of the university at 14:30 on 25/11/2026.', 'DE', 'UA', False, 'ALLOW', B, 'Room, time and date, not personal data.'),
]

out = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'test_messages.jsonl')
with open(out, 'w', encoding='utf-8', newline='\n') as f:
    for i, (text, sc, rc, att, expected, category, reason) in enumerate(rows, 1):
        f.write(json.dumps({'id': 'm%03d' % i, 'text': text, 'sender_country': sc, 'receiver_country': rc,
                            'has_attachment': att, 'expected': expected, 'category': category, 'reason': reason},
                           ensure_ascii=False) + '\n')

cats = {}
for r in rows: cats[r[5]] = cats.get(r[5], 0) + 1
n = len(rows); risky = n - cats['safe'] - cats['borderline']
print('%d messages: safe %d (%.0f%%), risky %d (%.0f%%), borderline %d (%.0f%%)' % (
    n, cats['safe'], 100 * cats['safe'] / n, risky, 100 * risky / n, cats['borderline'], 100 * cats['borderline'] / n))
print(cats)
