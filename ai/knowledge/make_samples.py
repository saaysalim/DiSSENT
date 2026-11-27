"""Writes the SAMPLE knowledge files (one per country). Not real legal text.

Each country gets the same six topics so the pipeline can be tested; the wording differs
a little per country so search results can be told apart. Run once:
    python ai/knowledge/make_samples.py
"""
import os

COUNTRIES = {
    'IE': ('Ireland', 'to be replaced by: GDPR and the Data Protection Act 2018'),
    'DE': ('Germany', 'to be replaced by: GDPR and the Federal Data Protection Act (BDSG)'),
    'UA': ('Ukraine', 'to be replaced by: Law of Ukraine "On Personal Data Protection"'),
    'IR': ('Iran', 'to be confirmed with the supervisor (no general data protection law)'),
    'AF': ('Afghanistan', 'to be confirmed with the supervisor (laws in force unclear)'),
}

TOPICS = [
    ('PD', 'Personal data',
     'SAMPLE summary, not legal text. In {name}, sharing personal data about another person, such as a phone number, '
     'private email address, date of birth, passport or national ID number, needs a clear reason and should be '
     'limited to what is necessary. Sharing it without the person\'s knowledge can put them at risk.'),
    ('SRC', 'Protection of sources',
     'SAMPLE summary, not legal text. In {name}, journalists are expected to protect confidential sources. Naming a '
     'source, or giving details such as a unique job, family relation or workplace that make a source identifiable, '
     'can expose the source to harm and should be avoided.'),
    ('LOC', 'Location and movement',
     'SAMPLE summary, not legal text. In {name}, an exact address, GPS coordinates, the location of a shelter or safe '
     'house, or the time and place of a planned border crossing can endanger people if the message is seen by others.'),
    ('FIN', 'Financial information',
     'SAMPLE summary, not legal text. In {name}, bank account numbers (IBAN), payment card numbers, crypto wallet '
     'addresses and payment codes are sensitive financial data. They can be used for fraud or to trace people.'),
    ('SEC', 'Account security',
     'SAMPLE summary, not legal text. In {name}, passwords, login details and links that ask people to log in on '
     'another website must not be shared in messages. Shared or reused passwords let others take over accounts.'),
    ('ATT', 'Attachments',
     'SAMPLE summary, not legal text. In {name}, files such as photos and documents can contain faces, places and '
     'hidden details. Their content cannot be checked automatically, so a person should look at them first.'),
]

base = os.path.dirname(os.path.abspath(__file__))
for code, (name, source) in COUNTRIES.items():
    os.makedirs(os.path.join(base, code), exist_ok=True)
    lines = ['title: SAMPLE privacy and safety rules for %s' % name,
             'source: %s' % source,
             'country: %s' % code,
             'status: SAMPLE', '']
    for tag, topic, text in TOPICS:
        lines += ['## %s-%s-1 %s' % (code, tag, topic), text.format(name=name), '']
    with open(os.path.join(base, code, 'sample_rules.md'), 'w', encoding='utf-8', newline='\n') as f:
        f.write('\n'.join(lines))
    print('wrote', code)
