INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Demo Trading','What is demo trading?','Demo trading is a practice environment that uses virtual funds and illustrative or seeded values. It helps explain platform layouts and common terminology; it does not reproduce every condition of live execution.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='What is demo trading?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Demo Trading','Are real funds used on this website?','No. This public project is a demonstration. It does not accept deposits or withdrawals and no real funds are used.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Are real funds used on this website?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Demo Trading','Are the market prices live?','No. The market catalogue currently shows static values seeded in the project database. A live market-data provider is not connected.','published',3
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Are the market prices live?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Demo Trading','Can I place real trades?','No. Order execution, broker connectivity and real trading are not implemented in this phase.','published',4
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Can I place real trades?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Accounts','Can I open or reset a demo account now?','Registration, account creation and balance reset are planned for later phases. The account pages currently explain demo profile concepts only.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Can I open or reset a demo account now?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Accounts','Do demo accounts expire?','Expiry rules are not configured because demo account management is not active yet.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Do demo accounts expire?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Accounts','Which virtual starting balances are shown?','The account page shows illustrative profile amounts. The current site setting defines a default demo balance of $100,000; account creation is not available yet.','published',3
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Which virtual starting balances are shown?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Markets','Which markets can I explore?','The seeded catalogue includes forex, metals, indices, commodities and digital assets. Stocks have a page but no stock instruments are configured yet. Availability reflects the active database catalogue.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Which markets can I explore?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Markets','What does spread mean on the market pages?','The displayed demo spread is the configured absolute difference between the seeded ask and bid values. It is not a live or guaranteed trading cost.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='What does spread mean on the market pages?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Platform','Can I use the platform on a phone?','The public website and platform preview are responsive for browser use on desktop, tablet and mobile. Native mobile applications are not available.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Can I use the platform on a phone?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Platform','What are stop loss and take profit?','They are order controls used to define potential exit levels. This website only describes them as future demo platform features; it does not accept or execute those orders.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='What are stop loss and take profit?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Platform','What is margin?','Margin is a portion of a position notional used in a leveraged trading context. The public margin calculator is an educational estimate based on configured demo data and does not represent account terms.','published',3
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='What is margin?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Security','How is my account protected?','Account registration and authentication are not implemented yet, so this project does not currently hold user account passwords. The security page describes implemented application foundations and future controls separately.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='How is my account protected?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Security','Can I submit identity documents for verification?','No. KYC and identity-document workflows are not active. Do not send identity documents through the contact form.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Can I submit identity documents for verification?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Support','How can I contact the project team?','Use the public contact form to send an enquiry. Messages are stored for future review. The current phase does not send email or promise a reply time.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='How can I contact the project team?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Funding','Can I deposit or withdraw money?','No. The demonstration does not accept deposits, process withdrawals or connect to payment providers.','published',1
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Can I deposit or withdraw money?');
INSERT INTO faqs (category,question,answer,status,display_order)
SELECT 'Funding','Is this site investment advice?','No. Market pages and calculators are educational demonstrations only. They are not investment advice or recommendations to buy or sell.','published',2
WHERE NOT EXISTS (SELECT 1 FROM faqs WHERE question='Is this site investment advice?');
