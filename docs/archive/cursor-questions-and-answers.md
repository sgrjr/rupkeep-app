# Cursor Questions and Ansers

## Critical Decisions:

1. Customer Portal Auth: Should customers be User records with role='customer', or a separate authentication system?

Lets use a singular auth system, because they may not utilize a tradintal email + password login, they will be required to put in a login code when veiwing an invoice index or an invidual invoice via the site and we will need a will to manage this like any other user. The permisssion should be a "white list" approach where we have 1) GUEST/UNAUTHENTICATED USER 2) EMPLOYEE:STANDARD 3) EMPLOYEE:MANAGER 4) CUSTOMER 5) ADMIN?SUPER USER

2. PDF Generation: Do you want actual PDF files (using dompdf library) or just print-optimized HTML?

Lets keep it simmple with print-optimized HTML and mark the use of the pdf library as a future feature.

3. Driver Notifications: Email only, SMS only, or both?

we will begin with email only and mark as future feature integrating with a service to allow both SMS and email so design should allow thi flexibility. Laravel's Event+Notification pattern is robust for this use case.

4. QuickBooks: Do you need API integration or just CSV export in QuickBooks format?

a csv export is what is needed.

## Nice-to-Know:

1. One-Time Login Links: How long should they be valid? Access to one invoice or all?

these links should be valid for a configured amount of time with a deafult of 24 hours. the login will give access depending on the authenticated/login role+permissions. if it is a customer that means they can view any of their invoices so they will access an index of all their invoices and view an invoice in detail and be able to comment on any invoice or flag it for attention. Flag+Text comment for requesting clarification or changes. Invoices will have accompanying "proof materials" like the logs and their attached images, files, etc. These proof materials are only viewable by staff unless staff marks them as "public" which allows the customer then to see the details of these documents. This will be a feature built in a business polilcy that will change overtime on when certain documents can be viewed by customers other than the invoice.

2. UI Redesign: Any websites/apps you like the look of? Should I keep the orange theme?

This is a pilot car company that provides escort vehicles to wide loads and other construction related transportation so the orange is very much on brand. Lets keep the orange theme and keep the construction "bright" theme. I like the thematic coloring of https://www.singleparentproject.org/ in particular how it uses the primary orange without over doing it.

3. Vehicle Maintenance: Is this a launch blocker or can it wait?

Vehicle maintenace should be integrated in the initial launch but we can discuss first feature set to launch. At a minimimm, Employees + Staff should be able to easily view & mnaage vehicle maintenance: oil and inspection dates, repair dates, scheduled maintenance, who currently has the vehicle assigned to them etc. this shouldn't be too complex of an initial feature. this is an "inventory management" feature of sorts.

4. Target Ship Date: When do you need this live?

The target Ship DAte is Noveber 21, 2025. We will need to break down a daily schedule as a target as this is not my primary job but a side hustle. I won't be able to work on the project 8 hours a day, but will be more like 2-3 hours a day with some full days thrown in as needed.