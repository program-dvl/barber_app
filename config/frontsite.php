<?php

return [
    'features' => [
        'online-booking' => [
            'label' => 'Online booking',
            'seo_title' => 'Online booking software for service businesses | ClipperDesk',
            'image' => '/images/marketing/editorial/use-cases-front-desk.webp',
            'title' => 'Book appointments online',
            'description' => 'Let clients book appointments on their phone, choosing services, staff and available times.',
            'definition' => 'Clients reserve appointments using the same availability and booking rules as your front desk.',
            'screens' => [
                'booking',
            ],
            'scenario' => [
                'title' => 'A client books while your team is busy',
                'body' => 'A client opens your booking link on a phone, chooses a published service and professional, and sees available times. Your front desk uses the same saved availability when booking by phone. Publish accurate staff hours and service durations before sharing the link.',
            ],
            'faqs' => [
                [
                    'question' => 'Does the client need an account?',
                    'answer' => 'No. Clients can book on a phone without creating an account. Private appointment links let them view or change a booking within the permitted rules.',
                ],
                [
                    'question' => 'Can I take deposits online?',
                    'answer' => 'Online deposit collection is currently unavailable. When a booking requires a deposit, the client must contact your business to finish.',
                ],
                [
                    'question' => 'Will this work beyond hair and beauty?',
                    'answer' => 'The same service, staff and availability workflow can fit other appointment-led businesses. Check the relevant industry page for specialist limitations before choosing.',
                ],
                [
                    'question' => 'Does booking automatically send every reminder?',
                    'answer' => 'Confirmations and reminders depend on the delivery services enabled for your business. Text messages also require consent, supported numbers and available credits.',
                ],
            ],
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-07',
                'FR-09',
                'FR-10',
            ],
            'workflow' => [
                [
                    'title' => 'Show current choices',
                    'body' => 'Show the locations, services and staff you have made available for online booking.',
                ],
                [
                    'title' => 'Hold the selected time',
                    'body' => 'A selected time is held briefly while the client enters their details.',
                ],
                [
                    'title' => 'Check availability before confirming',
                    'body' => 'Check the services, policies and availability again before confirming the appointment.',
                ],
                [
                    'title' => 'Let clients manage their appointment',
                    'body' => 'Private links let clients view or change their appointment. Each link expires and permits only its intended action.',
                ],
            ],
            'proof' => [
                'Clients can book on their phone without an account.',
                'Choose a preferred professional or any available qualified team member.',
                'Waitlist openings are confirmed for the first eligible client to claim them.',
            ],
            'limitations' => [
                'Online deposit collection is not currently available. Clients with a required deposit must contact your business to finish booking.',
                'Clients use the booking link shared by a business after it publishes its page.',
            ],
            'related' => [
                'calendar-and-walk-ins',
                'client-management',
            ],
        ],
        'calendar-and-walk-ins' => [
            'label' => 'Calendar and walk-ins',
            'seo_title' => 'Calendar and walk-in software for service businesses | ClipperDesk',
            'image' => '/images/marketing/industries/barbershop.webp',
            'title' => 'Manage appointments and walk-ins together',
            'description' => 'Coordinate staff schedules, appointment changes and waiting clients. Understand how walk-ins differ from requests for future openings.',
            'definition' => 'The calendar brings scheduled visits, staff availability and walk-ins into one view.',
            'screens' => [
                'calendar',
            ],
            'scenario' => [
                'title' => 'The next walk-in cannot delay a booked visit',
                'body' => 'Check the whole service window before assigning the next waiting client. A professional who is free now may have a booking before that service would finish. Keep staff hours, breaks and appointment changes in the same calendar.',
            ],
            'faqs' => [
                [
                    'question' => 'Are waiting times guaranteed?',
                    'answer' => 'No. Queue times are estimates that can change with service delays and staff availability. Review the current schedule before promising a start.',
                ],
                [
                    'question' => 'Can everyone change the calendar?',
                    'answer' => 'Actions depend on each team member’s permissions. Staff and space conflicts are checked when appointments are saved.',
                ],
                [
                    'question' => 'Can I manage a salon cancellation waitlist?',
                    'answer' => 'Customers can request future openings and claim suitable time-limited offers through private links. There is no general owner waitlist dashboard or bulk campaign. See Help Center’s waitlist guide for the customer journey and limits.',
                ],
            ],
            'requirements' => [
                'FR-03',
                'FR-05',
                'FR-06',
                'FR-07',
                'FR-08',
            ],
            'workflow' => [
                [
                    'title' => 'See the day in useful views',
                    'body' => 'View today, a day or week, or separate staff schedules. Filter by location, professional, service or status.',
                ],
                [
                    'title' => 'Update appointments',
                    'body' => 'Reschedule appointments, assign staff, change services, mark no-shows or block time.',
                ],
                [
                    'title' => 'Fit walk-ins around appointments',
                    'body' => 'See estimated waits and check staff and space before starting a walk-in service.',
                ],
                [
                    'title' => 'Recover from exceptions',
                    'body' => 'Review appointments affected by late arrivals, delays, staff absences or closures.',
                ],
                [
                    'title' => 'Distinguish a future opening from a walk-in',
                    'body' => 'The cancellation waitlist lets customers request a suitable future time and respond to an expiring offer. The walk-in queue is for arrivals waiting at reception. An offer is not a confirmed booking, and neither workflow guarantees a filled slot.',
                ],
            ],
            'proof' => [
                'Appointment labels and icons show status alongside colour.',
                'If someone else updates an appointment, review the latest version before saving.',
                'Managers can approve policy exceptions but cannot double-book staff or space.',
            ],
            'limitations' => [
                'Wait times are estimates and can change with service delays or staff availability.',
                'Existing room and equipment requirements are checked where configured. The service editor does not provide advanced resource or treatment-layout editing.',
            ],
            'related' => [
                'online-booking',
                'checkout-and-reporting',
            ],
        ],
        'client-management' => [
            'label' => 'Client management',
            'seo_title' => 'Client management software for service businesses | ClipperDesk',
            'image' => '/images/marketing/industries/nail-salon.webp',
            'title' => 'Keep client records, notes and visit history together',
            'description' => 'Find past visits, preferences, notes, forms and files in one client profile, with access controlled by role.',
            'definition' => 'Client management software gives your team one place to find a returning client’s visits, consultation notes, preferences and recorded purchases. Salons and barbershops can keep useful handoff details together while restricting sensitive notes to authorised people.',
            'screens' => [
                'clients',
            ],
            'scenario' => [
                'title' => 'A returning salon client sees a different stylist',
                'body' => 'Open the existing profile, review the previous visit and a permitted formula or preference note, then book the next service against that same client. Requested forms retain their submitted wording even after the template changes. This is client history, not a clinical planning system.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I bring existing client records?',
                    'answer' => 'Client CSV imports let you review invalid rows and possible duplicates before adding valid records. Check the available fields and your responsibilities before moving personal information.',
                ],
                [
                    'question' => 'Is this a specialist clinical record system?',
                    'answer' => 'No. Client notes and forms do not make ClipperDesk a medical record system, and it does not claim healthcare regulatory compliance.',
                ],
                [
                    'question' => 'Will every staff member see all client records?',
                    'answer' => 'No. Visits, contact details, notes, forms and attachments follow the team member’s permissions. During your trial, review a fictional returning client as both reception and a professional before importing real records.',
                ],
            ],
            'requirements' => [
                'FR-11',
                'FR-12',
                'FR-13',
                'FR-19',
            ],
            'workflow' => [
                [
                    'title' => 'Find or add the client',
                    'body' => 'Reuse matching contact details. Review possible duplicates before merging them.',
                ],
                [
                    'title' => 'Save useful notes',
                    'body' => 'Keep preferences, allergies, formulas and consultation notes with their author and access permissions.',
                ],
                [
                    'title' => 'Request client forms',
                    'body' => 'Submitted forms keep the questions and wording the client answered, even if you edit the template later.',
                ],
                [
                    'title' => 'Track privacy work',
                    'body' => 'Track data exports, corrections, consent changes and deletion requests with deadlines and review history.',
                ],
            ],
            'proof' => [
                'Keep photos and PDFs private with access limited to authorised team members.',
                'Updating contact details preserves history and replaces old appointment links.',
                'Review linked visits and records before combining duplicate profiles.',
            ],
            'limitations' => [
                'Deletion and anonymisation requests require policy approval before they can be completed.',
                'ClipperDesk does not claim medical-record or healthcare regulatory compliance.',
            ],
            'related' => [
                'online-booking',
                'checkout-and-reporting',
            ],
        ],
        'checkout-and-reporting' => [
            'label' => 'Checkout and reporting',
            'seo_title' => 'Checkout and reporting for service businesses | ClipperDesk',
            'image' => '/images/marketing/editorial/pricing-decision.webp',
            'title' => 'Complete checkout and understand your reports',
            'description' => 'Record service payments, create receipts and trace report totals to the original sale.',
            'definition' => 'Review sales, tax, payments, commissions and tips using the records from completed checkout.',
            'screens' => [
                'checkout',
            ],
            'scenario' => [
                'title' => 'Explain the final total before recording payment',
                'body' => 'Review completed services, products, tax, discounts and tips. Record payments received by cash or an external payment service, issue a receipt and follow the saved sale into reporting.',
            ],
            'faqs' => [
                [
                    'question' => 'Does checkout charge a client’s card?',
                    'answer' => 'No. ClipperDesk records money received elsewhere. Use your payment service to charge cards or send card refunds, then record the result in the business.',
                ],
                [
                    'question' => 'Can I record a partial or split payment?',
                    'answer' => 'Yes. Record the amounts received by supported methods, or leave an unpaid balance. Adjustments preserve the original financial history.',
                ],
            ],
            'requirements' => [
                'FR-14',
                'FR-15',
                'FR-16',
                'FR-17',
                'FR-18',
            ],
            'workflow' => [
                [
                    'title' => 'Build checkout from the appointment',
                    'body' => 'Review services, products, taxes, discounts, tips and any deposit before recording payment.',
                ],
                [
                    'title' => 'Record payments received',
                    'body' => 'Record payments already received by cash, external card, bank transfer or another supported method. Record partial or split payments, or leave a balance to pay later.',
                ],
                [
                    'title' => 'Correct records while keeping their history',
                    'body' => 'Payment adjustments retain the original transaction and the reason for the change.',
                ],
                [
                    'title' => 'Trace totals to their source',
                    'body' => 'Use filters, printable reports and exports to review totals and their underlying records.',
                ],
            ],
            'proof' => [
                'Receipts and reports show the relevant currency and local time.',
                'Product sales update stock; refunds record whether products are returned or written off.',
                'Exports use your selected filters and include only records you can access.',
            ],
            'limitations' => [
                'Checkout records payments received elsewhere. It does not charge cards or send refunds to a bank or card provider.',
                'Inventory tools are limited; supplier purchasing and warehouse transfers are not available.',
            ],
            'related' => [
                'calendar-and-walk-ins',
                'client-management',
            ],
        ],
    ],
    'solutions' => [
        'barbershops' => [
            'label' => 'Barbershops',
            'seo_title' => 'Barbershop Software for Bookings & Walk-ins | ClipperDesk',
            'seo_description' => 'Manage barbershop bookings, walk-in queues and client history in one place. Explore the workflow, check pricing and start your ClipperDesk trial.',
            'title' => 'Fit walk-ins around the appointments you promised',
            'description' => 'See the booked day, keep arrivals in order and choose an eligible barber when the full service fits. Keep the haircut and checkout on the same visit.',
            'image' => '/images/marketing/industries/barbershop.webp',
            'image_alt' => 'A barber giving a precise haircut in a busy modern barbershop',
            'accent' => 'cobalt',
            'fit' => 'For barbershops combining booked clients and walk-ins, with staff making the final service decision.',
            'screens' => [
                'walk-ins',
                'calendar',
            ],
            'scenario' => [
                'title' => 'The Saturday queue meets a booked haircut',
                'body' => 'A walk-in asks for a haircut and beard service while a regular is due shortly. Add the requested service to the queue, review the estimated wait and check the full service window before starting. A preferred barber may mean a longer wait; explain the choice rather than promising an exact start.',
            ],
            'faqs' => [
                [
                    'question' => 'Can clients choose their barber online?',
                    'answer' => 'They can choose an available qualified professional or any available team member from your published services and staff.',
                ],
                [
                    'question' => 'Is the walk-in queue a cancellation waitlist?',
                    'answer' => 'No. The queue manages arrivals. A separate customer waitlist accepts requests for future openings and sends time-limited offers when suitable openings are matched.',
                ],
                [
                    'question' => 'Will Marketplace bring new clients?',
                    'answer' => 'Eligible published businesses can appear in ClipperDesk Marketplace. Local supply and customer demand vary; listing does not guarantee bookings.',
                ],
            ],
            'requirements' => [
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-08',
                'FR-11',
                'FR-14',
                'FR-15',
            ],
            'challenges' => [
                [
                    'title' => 'Keep arrivals visible',
                    'body' => 'The walk-in board keeps waiting and in-service visits together. Staff can record a preferred barber and review changes without maintaining a second whiteboard.',
                ],
                [
                    'title' => 'Check the whole service window',
                    'body' => 'Starting service checks eligibility, hours and booked work. A queue estimate is guidance; it does not reserve a chair or guarantee a start time.',
                ],
                [
                    'title' => 'Finish the visit without re-entering it',
                    'body' => 'Carry completed services to checkout, review the total, record payment received and issue a receipt. Returning clients keep their visit history.',
                ],
            ],
            'day' => [
                'Review booked appointments and barber hours before opening.',
                'Add the walk-in’s requested service and optional barber preference.',
                'Check availability and start the visit when the service fits; update delays as they happen.',
                'Record the payment you received and keep the service in the client’s history.',
            ],
            'features' => [
                'calendar-and-walk-ins',
                'checkout-and-reporting',
                'client-management',
            ],
            'limits' => [
                'Queue times are estimates, not guaranteed starts. Queue assignment alone does not reserve time; starting the visit rechecks availability.',
                'Checkout records payments received elsewhere. Card charging, provider refunds, memberships and automated marketing are unavailable.',
            ],
            'group' => 'Hair, beauty & studio services',
            'heading' => 'A free barber now may be booked in twenty minutes',
            'checks' => [
                'Add two sample walk-ins with different barber preferences.',
                'Try starting a service that overlaps the barber’s next appointment.',
                'Complete one visit and inspect its receipt and client history.',
            ],
            'final_title' => 'Try your Saturday queue',
        ],
        'salons' => [
            'label' => 'Salons',
            'seo_title' => 'Salon Booking & Business Management Software | ClipperDesk',
            'seo_description' => 'Manage salon appointments, stylist schedules and client preferences. Explore multi-service visits, setup help and your ClipperDesk trial.',
            'title' => 'Keep salon appointments connected from colour to checkout',
            'description' => 'Plan different service lengths, assign each service to the right stylist and find returning-client preferences before the consultation starts.',
            'image' => '/images/marketing/industries/home-salon-hero.webp',
            'image_alt' => 'A stylist consulting with a client in a polished modern salon',
            'accent' => 'coral',
            'fit' => 'For hair and beauty salons managing individual visits, several services and returning clients. Test complex colour sequences at the front desk before publishing them online.',
            'screens' => [
                'services',
                'clients',
            ],
            'scenario' => [
                'title' => 'Colour, a wash and a finish—with a clear handoff',
                'body' => 'In your trial, build the sequence your salon actually sells. Add each service to one front-desk appointment and choose its stylist. Review processing and cleanup separately, then check the next booking. A processing period only frees the stylist when the configured service allows it.',
            ],
            'faqs' => [
                [
                    'question' => 'Can a client book several services?',
                    'answer' => 'Yes, published services can be selected together. The front desk also has a staff selector for each service line. Test your actual sequence: this is not a promise that clients can build every specialist handoff online.',
                ],
                [
                    'question' => 'Is there a cancellation waitlist?',
                    'answer' => 'Yes. Customers can request a suitable future opening and respond to a time-limited offer. This differs from the walk-in queue for arrivals. There is no general owner-facing waitlist dashboard or guaranteed refill.',
                ],
                [
                    'question' => 'Can I move my existing client records?',
                    'answer' => 'Client CSV import includes validation and duplicate review. For upcoming bookings or other records, agree an assisted migration scope, timing and any cost with support before transferring data.',
                ],
            ],
            'requirements' => [
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-07',
                'FR-09',
                'FR-11',
                'FR-12',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Give colour processing its own time',
                    'body' => 'Services separates active, processing and cleanup time, with staff-specific overrides. Inspect the calendar before assuming another client fits while colour develops.',
                ],
                [
                    'title' => 'Book the whole visit at the front desk',
                    'body' => 'Add several service lines and choose staff for each. Availability is checked when saved; a handoff cannot silently double-book the next stylist.',
                ],
                [
                    'title' => 'Start the next consultation informed',
                    'body' => 'Find previous visits, formulas and preferences in the client profile. Notes and files remain available according to team permissions.',
                ],
            ],
            'day' => [
                'Set service lengths, prices and eligible stylists; preview the client booking choices.',
                'Create the visit with its services and staff. Check the complete sequence before confirming.',
                'Review prior notes at consultation and record useful updates for the next professional.',
                'Review performed services at checkout, record externally received payment and issue the receipt.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'client-management',
            ],
            'limits' => [
                'Online deposit collection is unavailable. Deposit-required bookings direct clients to your business; checkout does not charge cards or execute provider refunds.',
                'Prepaid packages, memberships and automated marketing campaigns are unavailable. Detailed segment-by-segment handoff editing is not a complete self-service workflow.',
            ],
            'group' => 'Hair, beauty & studio services',
            'heading' => 'One visit can need more than one pair of hands',
            'checks' => [
                'Book colour and a finish with different stylists, then inspect both schedules.',
                'Try a booking during processing and confirm whether that specific service releases the stylist.',
                'Find a sample formula as a permitted staff member, then record a sample external payment.',
            ],
            'final_title' => 'Try your salon’s service sequence',
        ],
        'independent-stylists' => [
            'label' => 'Independent stylists',
            'seo_title' => 'Booking Software for Independent Stylists | ClipperDesk',
            'seo_description' => 'Give clients a booking link, protect your working hours and keep visit notes together. Explore ClipperDesk for independent stylists and compare pricing.',
            'title' => 'Run your independent stylist diary between clients',
            'description' => 'Share one booking link, protect your working hours and keep the notes you need for the next visit—all in your web browser.',
            'image' => '/images/marketing/industries/independent-stylist.webp',
            'image_alt' => 'An independent stylist serving a client in a refined one-chair studio',
            'accent' => 'amber',
            'fit' => 'For a solo stylist with appointment-led services and a browser-based workflow. Compare the current one-location, one-bookable-person price before choosing.',
            'requirements' => [
                'FR-01',
                'FR-02',
                'FR-04',
                'FR-05',
                'FR-09',
                'FR-11',
                'FR-14',
                'FR-18',
            ],
            'challenges' => [
                [
                    'title' => 'Publish only what you are ready to offer',
                    'body' => 'Prepare services, prices and your own availability, then preview the booking page. Completing setup and publishing are separate steps.',
                ],
                [
                    'title' => 'Give clients one place to book',
                    'body' => 'Share your business booking link. Clients can choose a published service and available time without creating an account.',
                ],
                [
                    'title' => 'Remember the details that matter',
                    'body' => 'Keep returning-client preferences and service history in one profile. Record received payments and check your day’s totals without a separate appointment ledger.',
                ],
            ],
            'day' => [
                'Add yourself as the bookable professional and set services and working hours.',
                'Preview the booking page before sharing your link.',
                'Find today’s appointments and the next client’s preferences.',
                'Record received payment, issue a receipt and arrange the next appointment.',
            ],
            'features' => [
                'online-booking',
                'client-management',
                'checkout-and-reporting',
            ],
            'limits' => [
                'There is no native mobile app. Test the browser experience on the phone you use at work.',
                'Online card collection, memberships and marketing campaigns are unavailable. Marketplace visibility does not guarantee demand.',
            ],
            'group' => 'Hair, beauty & studio services',
            'screens' => [
                'booking',
                'hours',
            ],
            'heading' => 'You should not need an admin team to keep a diary',
            'checks' => [
                'Block a day off and confirm clients cannot choose it.',
                'Complete a sample booking from your phone.',
                'Compare pricing for one location and one bookable professional, including any SMS needs.',
            ],
            'scenario' => [
                'title' => 'A day off should stay a day off',
                'body' => 'Set the days and hours you work, add a break and preview your booking link. Try selecting a time outside those hours. Then make a sample booking during an available period and find it in your calendar. This is a practical first check before replacing messages with a booking link.',
            ],
            'faqs' => [
                [
                    'question' => 'Does an assistant count as a bookable person?',
                    'answer' => 'Subscription pricing is based on locations and bookable staff. A non-bookable administrative user does not count as a bookable professional; see Pricing for current rules.',
                ],
                [
                    'question' => 'Do I need a website?',
                    'answer' => 'You can share your ClipperDesk booking link directly. Preview the published services and business details before giving it to clients.',
                ],
                [
                    'question' => 'Can I bring my paper diary across?',
                    'answer' => 'Prepare clients and upcoming visits for review. Support can help agree a transfer plan; do not send client files in your first email. Keep the original diary until you have checked the result.',
                ],
            ],
            'final_title' => 'Try your own working week',
        ],
        'spas' => [
            'label' => 'Spas',
            'seo_title' => 'Spa Scheduling & Appointment Software | ClipperDesk',
            'seo_description' => 'Plan spa treatments, practitioner schedules and turnaround time. Explore guest preferences, room setup considerations and ClipperDesk pricing.',
            'title' => 'Give every spa treatment the time it needs',
            'description' => 'Plan treatments around practitioner hours, leave time to reset, and find guest preferences before the next appointment. Check room requirements before you go live.',
            'image' => '/images/marketing/industries/spa-sauna.webp',
            'image_alt' => 'A spa practitioner welcoming a guest in a modern spa and sauna setting',
            'accent' => 'amber',
            'fit' => 'For non-medical, appointment-led treatments. Shared rooms need a setup review; this is not a sauna admission, group session or resort-management system.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-07',
                'FR-11',
                'FR-12',
            ],
            'challenges' => [
                [
                    'title' => 'Leave time between treatments',
                    'body' => 'Set active and cleanup time in Services. The calendar includes cleanup so a guest departure does not become the next practitioner’s rushed arrival.',
                ],
                [
                    'title' => 'Make practitioner choice meaningful',
                    'body' => 'Assign treatments to qualified staff and set their working hours and breaks. Clients choose from the services and availability you publish.',
                ],
                [
                    'title' => 'Welcome a returning guest with context',
                    'body' => 'Open the existing client profile for previous visits and appropriate preferences. Requested forms retain the wording the guest answered.',
                ],
            ],
            'day' => [
                'Prepare treatment durations, cleanup and qualified practitioners. Review any room requirement with support.',
                'Let the guest select a published treatment and available practitioner through your booking link.',
                'Find the guest’s preferences and submitted forms before service; update the appointment as the visit progresses.',
                'Record payment received through your usual method, issue a receipt and book the next visit when agreed.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'client-management',
            ],
            'limits' => [
                'Room conflicts are checked only for requirements already configured. The current screens do not offer complete room allocation or equipment setup; confirm your arrangement with us before accepting room-dependent bookings.',
                'Online deposits, packages and memberships are unavailable. Use your payment service to collect money; ClipperDesk records received payments.',
            ],
            'group' => 'Treatments & individual sessions',
            'screens' => [
                'services',
                'calendar',
            ],
            'heading' => 'A quiet guest experience starts with a workable diary',
            'checks' => [
                'Set a treatment’s active and cleanup time and inspect its full calendar span.',
                'Try two practitioners needing the same room at the same time; do not go live until the conflict is correctly blocked.',
                'Open a returning guest’s record using the permissions your receptionist will have.',
            ],
            'scenario' => [
                'title' => 'Two practitioners. One treatment room.',
                'body' => 'An illustrative trial: create a 60-minute treatment with 15 minutes of cleanup. That is a 75-minute calendar span, not a promise of more bookings. Try a second practitioner at the same time. A room-dependent service is ready only when the correct room requirement is configured and the overlapping booking is rejected.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I allocate treatment rooms myself?',
                    'answer' => 'Existing configured room requirements are enforced during booking. Complete room setup and allocation are not available in the current self-service screens. Email support with your room/service arrangement, without guest information, to review fit.',
                ],
                [
                    'question' => 'Does this include spa payroll?',
                    'answer' => 'No. Commission and tip records are available for business reporting, but ClipperDesk does not calculate payroll taxes, run payroll or pay staff.',
                ],
                [
                    'question' => 'Can guests buy a treatment package?',
                    'answer' => 'No. Prepaid packages, membership billing and redemption are not implemented. Evaluate the individual-treatment workflow instead.',
                ],
            ],
            'final_title' => 'Try your treatment day',
        ],
        'nail-salons' => [
            'label' => 'Nail salons',
            'seo_title' => 'Nail Salon Booking & Scheduling Software | ClipperDesk',
            'seo_description' => 'Plan nail appointments with time for removal and nail art. Match services to technicians, remember client preferences and explore your ClipperDesk trial.',
            'title' => 'Give nail salon appointments enough time for the full service',
            'description' => 'Plan removal, a fresh set and add-ons with realistic lengths. Match services to eligible technicians and keep returning-client preferences easy to find.',
            'image' => '/images/marketing/industries/nail-salon.webp',
            'image_alt' => 'A nail technician serving a client at a bright contemporary nail bar',
            'accent' => 'coral',
            'fit' => 'For appointment-led nail services and front-desk walk-ins. Shared manicure or pedicure stations need a separate setup review before relying on space checks.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Make extras explicit',
                    'body' => 'Configured add-ons become separate appointment lines with their own time and price. Only eligible add-ons linked to selected services are offered.',
                ],
                [
                    'title' => 'Match skills to the booking',
                    'body' => 'Assign technicians to the services they perform. Availability checks qualified staff and working time rather than treating every technician as interchangeable.',
                ],
                [
                    'title' => 'Keep walk-ins and preferences in view',
                    'body' => 'Add arrivals to the walk-in queue and review estimated waits. Store useful colour and style notes with the human client for their next visit.',
                ],
            ],
            'day' => [
                'Prepare service and add-on times, prices and qualified technicians.',
                'Book the complete combination rather than assuming removal takes no time.',
                'Review the client’s preferences and update appointment progress.',
                'Record received payment and retain the performed services in client history.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'client-management',
            ],
            'limits' => [
                'Configured station requirements can block conflicts, but complete station allocation is not exposed in current setup screens. Confirm your layout before publishing station-dependent services.',
                'Memberships, packages and online deposits are unavailable. Walk-in wait estimates can change as work runs late.',
            ],
            'group' => 'Hair, beauty & studio services',
            'screens' => [
                'assignments',
                'walk-ins',
            ],
            'heading' => 'Removal and nail art change the appointment',
            'checks' => [
                'Book a removal plus fresh-set combination and inspect the total time.',
                'Try selecting a technician who is not assigned to that service.',
                'If stations are shared, rehearse two overlapping services after a setup review.',
            ],
            'scenario' => [
                'title' => 'A fresh set that also needs removal',
                'body' => 'Build your actual service combination in the trial. Link removal or an extra as an eligible add-on where appropriate, check its duration, and select a technician qualified for the whole visit. Try the same combination online before publishing it.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I add nail art to a booking?',
                    'answer' => 'Create an add-on with its own time and price and link it to eligible parent services. Test the complete combination and staff eligibility before publishing.',
                ],
                [
                    'question' => 'Does it automatically assign a manicure station?',
                    'answer' => 'Do not assume that. Booking enforces existing configured requirements, but the current product does not provide complete self-service station allocation.',
                ],
                [
                    'question' => 'Can I sell a monthly nail membership?',
                    'answer' => 'No. Membership charging, included-service balances and redemption are not implemented.',
                ],
            ],
            'final_title' => 'Try your full nail-service combination',
        ],
        'medspas' => [
            'label' => 'Medspas',
            'seo_title' => 'Medspa Appointment Software: Evaluate Fit | ClipperDesk',
            'seo_description' => 'Explore general appointment scheduling for medspa evaluation. Review workflow examples and clinical, privacy and suitability limits before choosing.',
            'title' => 'Evaluate medspa appointments before choosing your system',
            'description' => 'See the general booking and front-desk tools, then assess their limits. ClipperDesk is not a clinical medspa platform or a verified healthcare-compliant system.',
            'image' => '/images/marketing/industries/medspa.webp',
            'image_alt' => 'A skincare professional consulting with a client in a contemporary medspa',
            'accent' => 'cobalt',
            'fit' => 'An evaluation of general appointment administration only. Medspa suitability is unverified, including when clinical records are stored elsewhere.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-12',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Inspect the administrative diary',
                    'body' => 'Published services, eligible staff and working hours determine offered appointment times. Use this to assess a reception task, not clinical readiness.',
                ],
                [
                    'title' => 'See what a schedule change preserves',
                    'body' => 'Review the appointment and select a new available time. Changes stay connected to visit history; they do not update an external clinical system.',
                ],
                [
                    'title' => 'Separate payment recording from collection',
                    'body' => 'Checkout can record money received elsewhere and issue a receipt. There is no integrated patient payment, insurance or clinical billing proposition.',
                ],
            ],
            'day' => [
                'Map the personal information your reception process would need, without entering it.',
                'Ask your privacy and security reviewers to assess the product and contractual requirements.',
                'Use fictional data to rehearse a booking and a practitioner change.',
                'Proceed with real use only if your review establishes suitability; otherwise choose a specialist system.',
            ],
            'features' => [
                'online-booking',
                'client-management',
                'checkout-and-reporting',
            ],
            'limits' => [
                'ClipperDesk is not an electronic medical record system and makes no HIPAA or healthcare-compliance claim. Appointment data itself may be sensitive; separate clinical storage is not sufficient assurance.',
                'No charting, prescribing, treatment photography workflow, insurance billing or verified EHR integration. Business forms do not establish clinical consent or legal suitability.',
            ],
            'group' => 'Specialist fit review',
            'screens' => [
                'booking',
                'calendar',
            ],
            'heading' => 'Start with suitability, before the first real booking',
            'checks' => [
                'Identify whether client names and appointment types reveal health information.',
                'Confirm privacy, security and contractual requirements with qualified reviewers before using real data.',
                'Compare the fictional booking workflow with a specialist system’s clinical and payment requirements.',
            ],
            'scenario' => [
                'title' => 'A reception workflow evaluated with fictional clients',
                'body' => 'Use a fictional consultation service to inspect booking choices, practitioner hours and a calendar change. Do not enter patient identities, health details or clinical forms during evaluation. A separate clinical system does not establish that appointment data is suitable for ClipperDesk.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I use it alongside my EHR?',
                    'answer' => 'There is no verified EHR integration. Keeping clinical notes elsewhere does not establish that storing appointments here is appropriate; assess the full information flow first.',
                ],
                [
                    'question' => 'Are forms suitable for medical consent?',
                    'answer' => 'No clinical or legal suitability is claimed. Versioned business forms preserve submitted wording but do not verify informed consent.',
                ],
                [
                    'question' => 'Should I start a trial with patients?',
                    'answer' => 'No. Evaluate with fictional data until an independent privacy, security and contractual review establishes suitability.',
                ],
            ],
            'final_title' => 'Try your administrative workflow with fictional data',
        ],
        'massage' => [
            'label' => 'Massage',
            'seo_title' => 'Massage Scheduling & Booking Software | ClipperDesk',
            'seo_description' => 'Schedule individual massage sessions with time between clients. Explore practitioner availability, booking and suitability limits with ClipperDesk.',
            'title' => 'Plan massage appointments with time to turn the room around',
            'description' => 'Offer clear session lengths, show practitioner availability and keep useful preferences for returning clients. Review shared-room fit before publishing.',
            'image' => '/images/marketing/industries/massage.webp',
            'image_alt' => 'A massage therapist preparing a calm modern treatment room for a client',
            'accent' => 'amber',
            'fit' => 'For non-clinical individual massage appointments. Regulated massage practices must assess privacy and contractual suitability before storing any client information.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-07',
                'FR-09',
                'FR-11',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Offer clear session choices',
                    'body' => 'Create the session lengths you actually sell and assign eligible practitioners. Clients see published durations and prices before selecting a time.',
                ],
                [
                    'title' => 'Keep turnaround in the diary',
                    'body' => 'Use cleanup time for the changeover after a session. Review actual service configuration; a free practitioner does not prove a shared room is ready.',
                ],
                [
                    'title' => 'Prepare for the returning client',
                    'body' => 'Find prior visits and appropriate preferences in the client profile. Rebook an individual visit without claiming an automatically generated series.',
                ],
            ],
            'day' => [
                'Set session lengths, cleanup and practitioner working hours.',
                'Let clients choose a published session and eligible practitioner.',
                'Review appropriate preferences before the visit and update its progress.',
                'Record received payment and book a return visit individually when agreed.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'client-management',
            ],
            'limits' => [
                'Shared-room checks require existing configured requirements; complete room allocation is not available in the current setup screens.',
                'There are no clinical notes, healthcare compliance claims, automatic recurring series, packages or memberships.',
            ],
            'group' => 'Treatments & individual sessions',
            'screens' => [
                'services',
                'clients',
            ],
            'heading' => 'The session ends before the working time does',
            'checks' => [
                'Compare the client-facing duration with the full calendar span.',
                'Try a shared-room overlap with two practitioners after setup review.',
                'Rebook one sample client and check that their history remains together.',
            ],
            'scenario' => [
                'title' => 'A 60-minute session is not always a 60-minute slot',
                'body' => 'For an illustrative trial, give a 60-minute session 15 minutes of cleanup. Inspect the 75-minute calendar span and the next available appointment. If another practitioner shares the room, confirm its configured requirement blocks an overlapping visit too.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I schedule a weekly series?',
                    'answer' => 'There is no automatic recurring-series generator. Book or rebook each appointment and check its availability.',
                ],
                [
                    'question' => 'Can I keep treatment notes?',
                    'answer' => 'General client notes and preferences exist, but they are not clinical documentation. Review privacy suitability independently; use fictional information during evaluation.',
                ],
                [
                    'question' => 'Are appointment reminders available?',
                    'answer' => 'Email and SMS reminders are available when delivery is enabled. SMS also requires consent, a supported destination and available credits. There is no guarantee a reminder prevents a missed visit.',
                ],
            ],
            'final_title' => 'Try your session and turnaround routine',
        ],
        'fitness-recovery' => [
            'label' => 'Fitness & recovery',
            'seo_title' => 'Personal Training & Recovery Scheduling | ClipperDesk',
            'seo_description' => 'Organise one-to-one fitness and recovery appointments. Explore coach availability, equipment setup considerations and booking with ClipperDesk.',
            'title' => 'Organise one-to-one fitness and recovery appointments',
            'description' => 'Give individual sessions a clear time and coach, keep changes visible and record payments received. Check equipment requirements before taking bookings.',
            'image' => '/images/marketing/industries/fitness-recovery.webp',
            'image_alt' => 'A coach guiding a client in a modern fitness and recovery studio',
            'accent' => 'lime',
            'fit' => 'For appointment-led, one-to-one non-clinical services. ClipperDesk is not a gym membership, group-class or training-programme platform.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-14',
                'FR-18',
            ],
            'challenges' => [
                [
                    'title' => 'Publish individual sessions clearly',
                    'body' => 'Set a duration and eligible coach for each service. The booking flow offers individual appointments, not class places or bootcamp enrolment.',
                ],
                [
                    'title' => 'Keep coach hours realistic',
                    'body' => 'Working hours, breaks and existing appointments shape availability. Review a changed session in the calendar rather than maintaining a separate message thread as your diary.',
                ],
                [
                    'title' => 'Keep a record of the commercial visit',
                    'body' => 'Carry performed services into checkout and record money received through your usual payment method. Reports refer back to those saved sales.',
                ],
            ],
            'day' => [
                'Define individual services, durations and eligible coaches.',
                'Review shared equipment needs and test conflicts before publishing.',
                'Book the client into available coach time and manage changes in Calendar.',
                'Record received payment and schedule any return session separately.',
            ],
            'features' => [
                'calendar-and-walk-ins',
                'online-booking',
                'checkout-and-reporting',
            ],
            'limits' => [
                'Group classes, bootcamp places, recurring session series, packages and membership billing are unavailable.',
                'Equipment checks require configured requirements and setup review. Device control, clinical recovery plans and outcome tracking are unavailable.',
            ],
            'group' => 'Treatments & individual sessions',
            'screens' => [
                'calendar',
                'assignments',
            ],
            'heading' => 'An individual session is a different job from a class',
            'checks' => [
                'Book an individual session and test the coach’s break boundary.',
                'List any shared device and prove an overlap is rejected before relying on it.',
                'Confirm that separately booking return visits fits your business model.',
            ],
            'scenario' => [
                'title' => 'One coach, one client and a shared recovery device',
                'body' => 'Set up the individual session and coach eligibility. If the session needs shared equipment, ask for a setup review and rehearse a conflicting booking. A coach’s open diary alone is not enough evidence that the device can be used.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I run a bootcamp or class?',
                    'answer' => 'No. There is no group enrolment, class-capacity or course-management workflow.',
                ],
                [
                    'question' => 'Does it store workout programmes?',
                    'answer' => 'No. It manages appointment administration and general client records, not exercise programming or clinical recovery plans.',
                ],
                [
                    'question' => 'Can clients buy ten sessions?',
                    'answer' => 'There is no prepaid session balance or package redemption. Payments are recorded against individual sales.',
                ],
            ],
            'final_title' => 'Try your one-to-one session workflow',
        ],
        'physical-therapy' => [
            'label' => 'Physical therapy',
            'seo_title' => 'Physical Therapy Scheduling: Evaluate Fit | ClipperDesk',
            'seo_description' => 'Evaluate general scheduling for physical therapy appointments with fictional visit examples. Review clinical, insurance and privacy limitations first.',
            'title' => 'Review physical therapy appointments against your practice needs',
            'description' => 'Inspect general scheduling with clear boundaries: no EHR, clinical planning, insurance billing or verified healthcare suitability.',
            'image' => '/images/marketing/industries/physical-therapy.webp',
            'image_alt' => 'A physical therapist guiding a client through a supervised mobility exercise',
            'accent' => 'lime',
            'fit' => 'General appointment administration can be evaluated, but suitability for a physical therapy practice is unverified. Physiotherapy and physical therapy do not become supported clinical workflows through scheduling alone.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-13',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Compare different appointment lengths',
                    'body' => 'Service duration and eligible staff shape the diary. Evaluate an initial and follow-up slot without entering patient information.',
                ],
                [
                    'title' => 'Check changes against working hours',
                    'body' => 'Calendar changes recheck availability. There is no automatic recurring treatment-series generation or clinical scheduling priority.',
                ],
                [
                    'title' => 'Identify the systems you would still need',
                    'body' => 'Clinical records, treatment plans, insurance and regulated patient communication require a suitable specialist workflow. No integration is implied.',
                ],
            ],
            'day' => [
                'List scheduling, privacy, clinical and insurance requirements before evaluating.',
                'Use fictional appointment types and people to inspect available times.',
                'Rebook a sample follow-up and check the practitioner’s working hours.',
                'Have qualified reviewers assess suitability before considering real patient data.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'checkout-and-reporting',
            ],
            'limits' => [
                'ClipperDesk is not an EMR/EHR and makes no HIPAA or healthcare regulatory claim. Patient names and appointment details can still be sensitive without clinical notes.',
                'No care plans, clinical documentation, insurance claims, authorisation tracking, treatment outcomes or automatic recurring series.',
            ],
            'group' => 'Specialist fit review',
            'screens' => [
                'calendar',
                'hours',
            ],
            'heading' => 'A practice needs more than an available appointment',
            'checks' => [
                'Confirm whether clinical scheduling and insurance authorisations are essential to your workflow.',
                'Rehearse separate initial and follow-up appointments using fictional people.',
                'Complete privacy, security and contract review before storing patient details.',
            ],
            'scenario' => [
                'title' => 'An initial visit and a follow-up—using fictional data',
                'body' => 'Create two sample service lengths and inspect practitioner availability. Rebook a follow-up individually. This demonstrates general scheduling only: it does not create a care plan, track authorised visits or submit an insurance claim.',
            ],
            'faqs' => [
                [
                    'question' => 'Is this physical therapy practice-management software?',
                    'answer' => 'It is general appointment and business software, not a specialist physical therapy platform. This page helps you assess the administrative scope and the missing clinical requirements.',
                ],
                [
                    'question' => 'Does it support pre-visit clinical planning?',
                    'answer' => 'No. Business forms are not clinical planning, chart review or treatment-plan software.',
                ],
                [
                    'question' => 'Can I book a course of appointments?',
                    'answer' => 'There is no automatic series generator or course tracking. Individual follow-ups can be booked separately, but that may not meet a practice’s requirements.',
                ],
            ],
            'final_title' => 'Try your administrative workflow with fictional data',
        ],
        'health-practices' => [
            'label' => 'Health practices',
            'seo_title' => 'Wellness Practice Scheduling: Evaluate Fit | ClipperDesk',
            'seo_description' => 'Explore general scheduling for non-clinical wellness appointments. Review privacy, specialist requirements and suitability before choosing ClipperDesk.',
            'title' => 'Check whether your health practice needs a specialist system',
            'description' => 'Explore the appointment tools with fictional data. General scheduling and business forms do not establish suitability for healthcare or regulated client information.',
            'image' => '/images/marketing/industries/health-practice.webp',
            'image_alt' => 'An independent practitioner consulting with a client in a welcoming modern practice',
            'accent' => 'cobalt',
            'fit' => 'Potential evaluation for non-clinical, appointment-led wellness administration only. No healthcare profession or regulated workflow is certified as suitable by this page.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-12',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Inspect a simple booking journey',
                    'body' => 'Clients choose published services and available staff time. This is an administrative workflow; it does not assess symptoms or triage care.',
                ],
                [
                    'title' => 'Define what staff would see',
                    'body' => 'Client records have permission controls, but access controls alone do not establish regulatory compliance or appropriate retention.',
                ],
                [
                    'title' => 'Check the handoff to other systems',
                    'body' => 'No clinical integration is verified. Determine whether separate manual administration would be appropriate before choosing the product.',
                ],
            ],
            'day' => [
                'Describe your practice, appointment types and information requirements without sharing client records.',
                'Review privacy, security, retention and contractual requirements independently.',
                'Rehearse a simple booking and change with fictional data.',
                'Choose only after confirming that the product’s limits fit your actual service.',
            ],
            'features' => [
                'online-booking',
                'client-management',
                'checkout-and-reporting',
            ],
            'limits' => [
                'No diagnosis, clinical notes, prescribing, insurance billing or EMR/EHR capability. ClipperDesk makes no healthcare-compliance claim.',
                'Keeping clinical records elsewhere does not establish suitability. Names, service choices and reminder content also need privacy and security review.',
            ],
            'group' => 'Specialist fit review',
            'screens' => [
                'booking',
                'hours',
            ],
            'heading' => 'Define the information you need to handle first',
            'checks' => [
                'Write down what each appointment type could reveal about a person.',
                'Identify clinical integrations, retention or insurance requirements that cannot be met here.',
                'Ask about fit using business requirements only, not client or patient records.',
            ],
            'scenario' => [
                'title' => 'A non-clinical wellness appointment, with a privacy check',
                'body' => 'A non-clinical appointment-led wellness service may need only published services and staff hours. Even then, identify what the client’s name, service and reminders reveal. Evaluate with fictional data and review suitability rather than assuming a “non-clinical” label removes the risk.',
            ],
            'faqs' => [
                [
                    'question' => 'Which health practices are supported?',
                    'answer' => 'There is no certified list of healthcare professions. General non-clinical wellness administration can be evaluated, but each organisation must establish suitability before real use.',
                ],
                [
                    'question' => 'Do permissions make it healthcare compliant?',
                    'answer' => 'No. Permissions are one product control, not proof of legal, regulatory or contractual suitability.',
                ],
                [
                    'question' => 'Can I use business forms for pre-visit planning?',
                    'answer' => 'Forms can collect configured business information with version history. They do not perform clinical pre-visit planning or establish medical consent.',
                ],
            ],
            'final_title' => 'Try your administrative workflow with fictional data',
        ],
        'tattoo-piercing' => [
            'label' => 'Tattooing & piercing',
            'seo_title' => 'Tattoo & Piercing Appointment Software | ClipperDesk',
            'seo_description' => 'Organise studio consultations, artist schedules and client visits. Explore ClipperDesk booking and the consent and age-verification limits to review.',
            'title' => 'Keep tattoo and piercing appointments clear from consultation onward',
            'description' => 'Give consultations and studio sessions their own time, choose eligible artists and keep business forms and received-payment records with the visit.',
            'image' => '/images/marketing/industries/tattoo-piercing.webp',
            'image_alt' => 'A tattoo artist preparing a stencil with a client in a clean contemporary studio',
            'accent' => 'coral',
            'fit' => 'For appointment-led studio administration. Specialist consent, age verification and regulatory processes must be established separately.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-12',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Set the right session length',
                    'body' => 'Give each service its own duration and eligible artists. Check the full appointment window rather than fitting a long session into a short consultation slot.',
                ],
                [
                    'title' => 'Keep the submitted wording',
                    'body' => 'Versioned business forms retain the questions and answers submitted for the visit. Your studio remains responsible for lawful consent, age checks and aftercare.',
                ],
                [
                    'title' => 'Explain the balance at checkout',
                    'body' => 'Review service items and payments already received. Record external payments and issue a receipt; ClipperDesk does not collect a card deposit.',
                ],
            ],
            'day' => [
                'Prepare consultation and session services with eligible artists.',
                'Book the agreed service and confirm time and any station requirements.',
                'Complete your own required identity, age and consent process; retain appropriate business records.',
                'Record received payments and issue a receipt for the completed visit.',
            ],
            'features' => [
                'online-booking',
                'client-management',
                'checkout-and-reporting',
            ],
            'limits' => [
                'There is no automated age verification, legally certified consent or aftercare-compliance workflow. A business form does not replace these checks.',
                'Online deposit collection is unavailable. Shared station setup needs review; no complete station-allocation editor is provided.',
            ],
            'group' => 'Hair, beauty & studio services',
            'screens' => [
                'services',
                'checkout',
            ],
            'heading' => 'A consultation and a long session need different plans',
            'checks' => [
                'Book a consultation and a longer session and compare their calendar spans.',
                'Publish a sample business form, change the template and inspect the original submitted wording.',
                'Record a fictional payment received elsewhere and inspect the remaining balance.',
            ],
            'scenario' => [
                'title' => 'Consult first, then agree the session',
                'body' => 'Publish a consultation with a realistic length. Once the artist and client agree the scope, book the appropriate session separately. Keep useful business notes and record payments received through your usual method. Do not treat a completed form as proof that age or consent requirements have been met.',
            ],
            'faqs' => [
                [
                    'question' => 'Can I take a booking deposit online?',
                    'answer' => 'Not through the current public booking flow. A required-deposit booking directs the client to contact your studio. Record money received through your existing payment method.',
                ],
                [
                    'question' => 'Does a signed form verify the client’s age?',
                    'answer' => 'No. Forms and version history are not identity or age verification, nor a guarantee of legally valid consent.',
                ],
                [
                    'question' => 'Can I show my studio online?',
                    'answer' => 'Business booking presentation supports salon/studio imagery, and eligible published businesses can appear in Marketplace. This is not a specialist artist portfolio or a promise of new bookings.',
                ],
            ],
            'final_title' => 'Try your consultation-to-session workflow',
        ],
        'pet-grooming' => [
            'label' => 'Pet grooming',
            'seo_title' => 'Pet Grooming Appointment Software | ClipperDesk',
            'seo_description' => 'Explore simple grooming appointments, owner records and visit notes. Check the limits of pet profiles and multi-pet workflows before choosing ClipperDesk.',
            'title' => 'Plan pet grooming appointments around realistic service times',
            'description' => 'Organise groomer availability and owner contact details, with manually written pet context. Check the limits of owner-based records before moving your diary.',
            'image' => '/images/marketing/industries/pet-grooming.webp',
            'image_alt' => 'A professional groomer brushing a dog in a bright modern pet grooming studio',
            'accent' => 'yellow',
            'fit' => 'A limited fit for simple appointment-led grooming that can work with owner records and manual visit notes. Multi-pet or vaccination-led businesses may need specialist grooming software.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-08',
                'FR-09',
                'FR-11',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Use service lengths that fit the job',
                    'body' => 'Create realistic service choices and choose qualified groomers. Timing is configured by your team; the product does not calculate duration from breed, coat or behaviour.',
                ],
                [
                    'title' => 'Keep owner contact and visit notes together',
                    'body' => 'The client profile belongs to the human owner. Write necessary pet-related context on the visit; do not mistake those notes for structured pet records.',
                ],
                [
                    'title' => 'Keep the front desk informed',
                    'body' => 'Use appointment status to follow the visit and record received payment at checkout. Arrange pickup using your existing process; there is no verified automated pet-ready alert.',
                ],
            ],
            'day' => [
                'Define grooming services and durations based on your own assessment.',
                'Book an eligible groomer against the human owner’s record.',
                'Write clear pet context on the visit and use your own pickup communication process.',
                'Record received payment and review owner-linked visit history.',
            ],
            'features' => [
                'online-booking',
                'calendar-and-walk-ins',
                'client-management',
            ],
            'limits' => [
                'There are no dedicated pet profiles, owner-to-multiple-pet relationships, per-pet medical records or vaccination tracking. Human client notes are not a substitute for these workflows.',
                'No route planning, kennelling, automatic breed-based timing or verified pet-ready pickup automation. Shared station requirements need setup review.',
            ],
            'group' => 'Specialist fit review',
            'screens' => [
                'calendar',
                'clients',
            ],
            'heading' => 'The owner is the client record. The pet is not.',
            'checks' => [
                'Create two fictional visits for different pets belonging to one owner.',
                'Ask another staff role to identify the right pet and relevant visit notes.',
                'If separate pet history or vaccination enforcement is essential, treat that as a failed fit check.',
            ],
            'scenario' => [
                'title' => 'One owner brings two dogs',
                'body' => 'Use fictional owner and pet details to rehearse two separate grooming visits. Put clear pet context on each visit and check what a different groomer can find. There is no dedicated pet identity or per-pet history, so decide whether this manual distinction is sufficient before migrating.',
            ],
            'faqs' => [
                [
                    'question' => 'Is dog grooming different from pet grooming here?',
                    'answer' => 'Both use the same owner-linked appointment workflow. There is no separate dog profile or breed-aware booking engine.',
                ],
                [
                    'question' => 'Will it remind owners about pickup?',
                    'answer' => 'There is no verified dedicated pet-ready pickup automation. Use your established pickup process; appointment reminders are a separate conditional email/SMS feature.',
                ],
                [
                    'question' => 'Can I migrate pet records?',
                    'answer' => 'Do not assume a specialist pet database can transfer intact. Ask support to review the fields and limitations before providing files or committing to migration.',
                ],
            ],
            'final_title' => 'Try your owner-and-pet note routine',
        ],
        'tanning-studios' => [
            'label' => 'Tanning studios',
            'seo_title' => 'Spray Tan Booking & Studio Scheduling | ClipperDesk',
            'seo_description' => 'Schedule staff-led spray-tan visits with time to prepare between clients. Explore booking and recorded checkout; bed controls and memberships are excluded.',
            'title' => 'Keep appointment-led tanning studio visits organised',
            'description' => 'Schedule staff-led services with clear durations and recorded payments. Review room needs and device limitations before replacing a specialist tanning system.',
            'image' => '/images/marketing/industries/spray-tan-welcome.webp',
            'image_alt' => 'A studio professional welcoming a fully clothed client beside a spray-tan booth',
            'accent' => 'amber',
            'fit' => 'For individual, staff-led appointment administration such as spray-tan services. Not a bed-control, exposure-management or membership platform.',
            'requirements' => [
                'FR-03',
                'FR-04',
                'FR-05',
                'FR-06',
                'FR-09',
                'FR-11',
                'FR-12',
                'FR-14',
            ],
            'challenges' => [
                [
                    'title' => 'Publish a clear appointment choice',
                    'body' => 'Set a service duration, price and eligible staff. Clients choose from available appointments; this is not unattended device access.',
                ],
                [
                    'title' => 'Leave time to prepare for the next client',
                    'body' => 'Set cleanup time and inspect the calendar. Existing configured room requirements can be checked, but room setup must be reviewed separately.',
                ],
                [
                    'title' => 'Keep each sale understandable',
                    'body' => 'Record money received elsewhere against completed services and issue a receipt. No tanning-credit wallet or recurring membership is implied.',
                ],
            ],
            'day' => [
                'Define staff-led services, cleanup time and eligible staff.',
                'Review shared rooms and your separate safety/device processes before publication.',
                'Book the individual appointment and carry out required checks through your established process.',
                'Record externally received payment and retain the visit and receipt.',
            ],
            'features' => [
                'online-booking',
                'client-management',
                'checkout-and-reporting',
            ],
            'limits' => [
                'No bed timers, device controls, exposure calculations, automatic age verification or health-screening certification. Local safety obligations require a separate appropriate process.',
                'Membership billing, prepaid packages and credit redemption are unavailable. Room requirements need setup review; online deposits are unavailable.',
            ],
            'group' => 'Specialist fit review',
            'screens' => [
                'booking',
                'checkout',
            ],
            'heading' => 'Booking a session does not control a tanning device',
            'checks' => [
                'Book a sample staff-led session with cleanup and inspect the next available time.',
                'List every device, exposure and age-check requirement handled outside this product.',
                'If package balances or bed controls are essential, do not treat appointment scheduling as a replacement.',
            ],
            'scenario' => [
                'title' => 'A staff-led spray-tan appointment',
                'body' => 'Create an illustrative service with the staff time and cleanup you need. Preview the booking choices and review an example sale after service. If your business depends on bed timers, exposure limits or package balances, this appointment workflow does not replace them.',
            ],
            'faqs' => [
                [
                    'question' => 'Can it switch a tanning bed on or off?',
                    'answer' => 'No. There are no device-control or bed-timer integrations.',
                ],
                [
                    'question' => 'Can clients use a monthly unlimited plan?',
                    'answer' => 'No. Membership billing, package balances and redemption are not implemented.',
                ],
                [
                    'question' => 'Do forms enforce safe exposure or age limits?',
                    'answer' => 'No. Versioned business forms retain submitted wording, but they do not calculate safe exposure, verify age or certify compliance.',
                ],
            ],
            'final_title' => 'Try your staff-led appointment workflow',
        ],
    ],
    'use_cases' => [
        'reduce-scheduling-conflicts' => [
            'label' => 'Reduce scheduling conflicts',
            'seo_title' => 'How to reduce scheduling conflicts | ClipperDesk',
            'image' => '/images/marketing/editorial/product-workday.webp',
            'title' => 'Avoid scheduling conflicts',
            'description' => 'Keep online and front-desk bookings aligned with current staff hours, service durations and availability, with a final check before confirmation.',
            'answer' => 'Scheduling conflicts are best reduced by making every booking path use the same working hours, staff eligibility, service duration and resource rules. ClipperDesk briefly holds a selected time and checks availability again before confirmation. Accurate setup still matters, and unexpected delays need your team’s attention.',
            'requirements' => ['FR-03', 'FR-04', 'FR-05', 'FR-06', 'FR-07'],
            'symptoms' => ['A client sees a time the front desk believes is unavailable.', 'A room or piece of equipment is promised to two services.', 'A late edit silently overwrites a newer calendar change.'],
            'practice' => [
                ['title' => 'Write down the real constraints', 'body' => 'Keep location hours, staff working intervals, breaks, service segments, cleanup time and required resource quantities current.'],
                ['title' => 'Use one booking path', 'body' => 'Use the same availability checks for online bookings, front-desk appointments and rescheduling.'],
                ['title' => 'Recheck at the decision point', 'body' => 'Check availability again when reserving and confirming a time, because another booking may have taken it.'],
                ['title' => 'Review changes to the working day', 'body' => 'Record late arrivals, delays and staff absences so your team can identify affected appointments.'],
            ],
            'product_steps' => ['Show available times for the selected services and qualified staff.', 'Reserve the selected time briefly while the client finishes booking.', 'Check availability and appointment changes again before saving.', 'Ask the team to review a changed appointment before updating it.'],
            'limitations' => ['Good configuration still matters: outdated hours, eligibility or resource quantities can make otherwise-correct results unhelpful.', 'Unexpected closures and service overruns require an explicit recovery decision; software cannot guarantee a conflict-free day.'],
            'feature' => 'calendar-and-walk-ins',
            'solution' => 'salons',
        ],
        'manage-walk-ins-and-appointments' => [
            'label' => 'Manage walk-ins and appointments',
            'seo_title' => 'Manage walk-ins and appointments together | ClipperDesk',
            'image' => '/images/marketing/industries/barbershop.webp',
            'title' => 'Manage walk-ins alongside appointments',
            'description' => 'Keep walk-ins visible, review estimated waits and check staff availability before starting service around existing appointments.',
            'answer' => 'Add arriving clients to the queue, then check staff availability before assigning a service. ClipperDesk keeps the queue alongside scheduled appointments, so your team can fit walk-ins around booked work.',
            'requirements' => ['FR-06', 'FR-08'],
            'symptoms' => ['A verbal queue estimate competes with the next booked client.', 'The team cannot tell why one walk-in moved ahead of another.', 'Actual waits disappear, leaving future estimates to guess.'],
            'practice' => [
                ['title' => 'Capture the request consistently', 'body' => 'Record arrival, requested service, preferred or any qualified staff, and the client\'s notification choice.'],
                ['title' => 'Explain the queue order', 'body' => 'Use a visible ordering reason and treat estimated start time as changeable operating guidance.'],
                ['title' => 'Check the real calendar before assignment', 'body' => 'Confirm the service still fits around booked work and staff capacity at the moment of conversion.'],
                ['title' => 'Record how the visit ended', 'body' => 'Record whether each client was served, cancelled, left or did not respond, along with their actual wait.'],
            ],
            'product_steps' => ['Add a walk-in with service and staff preference.', 'Review estimated waits and the order of the queue.', 'Check availability before assigning staff and starting service.', 'Start service and preserve the queue-to-appointment history.'],
            'limitations' => ['A wait estimate is not a promise and should change when current work, staffing or service choice changes.', 'A walk-in cannot override a real staff or resource conflict; a manager may override policy warnings only.'],
            'feature' => 'calendar-and-walk-ins',
            'solution' => 'barbershops',
        ],
        'protect-time-with-deposits' => [
            'label' => 'Protect time with deposits',
            'seo_title' => 'Booking deposits for service businesses | ClipperDesk',
            'image' => '/images/marketing/editorial/pricing-decision.webp',
            'title' => 'Set clear deposit rules',
            'description' => 'Set deposit and cancellation rules, show clients the terms before confirmation and keep the agreed policy with their appointment.',
            'answer' => 'A deposit can help protect reserved time when the amount, due point, cancellation terms and refund handling are clear. ClipperDesk calculates deposit requirements and saves the policy shown at booking. Online deposit collection is not currently available; clients with a required deposit must contact your business. A deposit does not guarantee attendance.',
            'requirements' => ['FR-09', 'FR-10', 'FR-14'],
            'symptoms' => ['Clients do not see the cancellation terms until after booking.', 'A policy edit appears to change an older appointment.', 'The front desk cannot trace a deposit into the final balance or refund.'],
            'practice' => [
                ['title' => 'Choose a proportionate rule', 'body' => 'Decide which services require a deposit, how it is calculated and what happens after cancellation or no-show, with appropriate legal review.'],
                ['title' => 'Show the terms before consent', 'body' => 'Present the amount and cancellation policy in the booking flow before confirmation rather than relying on a later message.'],
                ['title' => 'Preserve what was accepted', 'body' => 'Save the policy and deposit requirement so future configuration edits do not rewrite the agreement.'],
                ['title' => 'Reconcile the money', 'body' => 'Keep payment and adjustment records linked to the appointment so the team can explain any remaining balance.'],
            ],
            'product_steps' => ['Use the business’s published booking policy.', 'Show the deposit amount before confirmation.', 'Direct clients to your business when a required deposit prevents online confirmation.', 'Review verified deposits at checkout and record payments received elsewhere.'],
            'limitations' => ['Online deposit collection is not currently available. A deposit-required booking cannot be confirmed online without verified payment.', 'ClipperDesk does not guarantee that deposits reduce no-shows or supply legal advice about cancellation terms.'],
            'feature' => 'online-booking',
            'solution' => 'independent-stylists',
        ],
        'keep-client-history-together' => [
            'label' => 'Keep client history together',
            'seo_title' => 'Keep salon client records and history together | ClipperDesk',
            'image' => '/images/marketing/industries/spa-sauna.webp',
            'title' => 'Keep client history together',
            'description' => 'Keep client visits, preferences, notes, forms, messages and purchases together, with sensitive information restricted to authorised team members.',
            'answer' => 'ClipperDesk connects each client’s visits, notes and purchases in one profile. Access permissions protect sensitive information, and your team reviews possible duplicates before combining profiles.',
            'requirements' => ['FR-11', 'FR-12', 'FR-13', 'FR-14', 'FR-19'],
            'symptoms' => ['Preferences and formulas live in personal notes that the next authorised professional cannot find.', 'Duplicate profiles split visits and receipts across identities.', 'A contact update loses the earlier audit trail or weakens secure links.'],
            'practice' => [
                ['title' => 'Collect only useful context', 'body' => 'Define why each field or note is needed, who can see it and how long it should remain.'],
                ['title' => 'Separate general from protected notes', 'body' => 'Use narrower access for sensitivities, formulas, warnings and consultation evidence instead of sharing everything equally.'],
                ['title' => 'Match and merge conservatively', 'body' => 'Reuse matching contact details and review linked records before merging possible duplicates.'],
                ['title' => 'Preserve requests and changes', 'body' => 'Track consent, contact changes and privacy cases with authorship and deadlines rather than erasing the evidence.'],
            ],
            'product_steps' => ['Open the business client profile from the appointment.', 'Review the visits, forms and notes you have permission to see.', 'Keep completed visits and their payment records in the client history.', 'Record contact changes, profile merges and privacy-request outcomes.'],
            'limitations' => ['Deletion and anonymisation requests can be reviewed, but final removal requires an approved retention policy.', 'ClipperDesk is not a medical-record system and does not claim healthcare regulatory compliance.'],
            'feature' => 'client-management',
            'solution' => 'salons',
        ],
    ],
    'guides' => [
        'salon-software-demo-scorecard' => [
            'seo_title' => 'Salon software demo checklist and printable scorecard | ClipperDesk',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'title' => 'Put salon booking software through a Saturday shift',
            'description' => 'Take three booking tests into your next salon software demo. Print a scorecard to compare which tools protect breaks, find appointments and handle walk-ins.',
            'topic' => 'Choosing salon software', 'published_at' => '2026-11-17', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-11-17',
            'intro' => 'Bring your receptionist, a phone and one fictional Saturday diary. Give every shortlisted system the same three jobs. By the end you should know what a client can book, what the desk can promise and which questions still need a live demonstration.',
            'sections' => [
                ['title' => 'Protect a break on a busy day', 'body' => 'Give Alex a shift from 09:00 to 15:00, a 12:00–12:30 break and a 45-minute cut. From the client booking page, look for 11:30 with Alex. That visit would finish inside lunch. Check that it cannot be confirmed, then try 12:30 as a control. Use an otherwise empty diary and a start interval that permits both times. If neither appears, ask the demonstrator to explain which setting ruled each one out.'],
                ['title' => 'Move a booking without losing it', 'body' => 'Book a fictional client at 10:00, then have the receptionist move the visit to an available 13:00 slot. Ask a colleague to find the visit without your help. Look for one current booking at 13:00, the original slot available again, and the same service and professional. Use a test inbox if you also want to check what the client hears. Record any manual follow-up the desk must do.'],
                ['title' => 'Answer a walk-in while a regular is due', 'body' => 'At 13:45 a walk-in asks for a 30-minute cut. Alex has a regular booked at 14:00. Have the desk show the earliest honest offer without moving the regular. If another qualified barber is free, try that option. If you work solo, ask how to record a later offer. Watch what the next receptionist sees after the handover and write down any detail they would have to remember.'],
            ],
            'checklist' => ['Circle the tests your shop must pass before seeing a demo.', 'For each test mark Pass, Workaround or Unseen. Write what happened.', 'For a workaround, name the person who would do the extra work each shift.', 'Repeat every unseen must-have yourself before choosing a system.'],
            'limitation' => 'Use fictional names and a test inbox. Ask for a practice workspace before creating appointments or sending reminders.',
            'feature' => 'calendar-and-walk-ins',
            'feature_label' => 'See the ClipperDesk calendar and walk-in queue',
            'worksheet' => [
                'title' => 'Your demo scorecard',
                'instruction' => 'Print one copy per system. Record the system and date, then mark each must-have as Pass, Workaround or Unseen.',
                'rows' => [
                    ['title' => 'System and date', 'prompt' => 'System · date · person testing'],
                    ['title' => 'Protect a break', 'prompt' => 'Must-have? · result · what the desk saw'],
                    ['title' => 'Move a booking', 'prompt' => 'Must-have? · result · what the desk saw'],
                    ['title' => 'Offer a walk-in a time', 'prompt' => 'Must-have? · result · what the desk saw'],
                    ['title' => 'Before you decide', 'prompt' => 'Question · person responsible · next demo date'],
                ],
            ],
            'sources_title' => 'Set up the lunch test in your shortlisted tools',
            'sources' => [
                ['label' => 'Square US guide to personal events', 'href' => 'https://squareup.com/help/us/en/article/5533-create-a-personal-event-on-your-square-appointments-calendar', 'detail' => 'In the Dashboard instructions, look for the Block time option when adding a personal event.'],
                ['label' => 'Fresha guide to blocked time', 'href' => 'https://www.fresha.com/help-center/knowledge-base/calendar/18-set-up-and-manage-blocked-time', 'detail' => 'Use this to find the settings for break or personal-time labels before running the lunch test.'],
                ['label' => 'Salonist guide to blocking staff time', 'href' => 'https://help.salonist.io/en/articles/9148659-can-we-add-blocked-time-for-staff-calendar', 'detail' => 'Follow the Staff Calendar example to block a named professional for a start and end time. Then check the client view yourself.'],
            ],
        ],
        'salon-client-migration-rehearsal' => [
            'title' => 'Rehearse your salon client-record move',
            'seo_title' => 'Salon client migration rehearsal worksheet | ClipperDesk',
            'description' => 'Test duplicate names, contact preferences and future visits on paper before moving salon client records. Includes a sample batch and a printable sign-off sheet.',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'topic' => 'Client records',
            'published_at' => '2026-11-17',
            'reviewed_at' => '2026-11-17',
            'reviewed_by' => 'ClipperDesk editorial team',
            'intro' => 'Give the receptionist a small fictional batch and ask them to find the right client and the right next visit. Run this rehearsal before choosing how to transfer live records.',
            'sections' => [
                [
                    'title' => 'Start with three people and two bookings',
                    'body' => 'Create three fictional profiles on paper: Rowan A and Rowan B share a display name but have different test contact details. Jules has a phone number and no recorded marketing preference. Give Rowan B a Friday 14:00 cut and Jules a Saturday 09:30 consultation. Record each visit’s location, local time zone and professional. Your expected totals are three people and two future visits.',
                ],
                [
                    'title' => 'Check the exceptions first',
                    'body' => 'In a practice workspace, review the available client import fields and preview your sample. Keep the two Rowans separate unless you have evidence they are one person. Keep Jules’s unknown preference unknown. A phone number alone does not establish permission to send marketing. If an expected field cannot be represented, write down who will resolve it before transferring that field.',
                ],
                [
                    'title' => 'Find each visit from the desk',
                    'body' => 'Ask a second person to find Rowan B and read back the correct visit. Repeat for Jules. Three client profiles alone do not prove that either appointment moved. Match time, service, location and professional separately. If a visit is missing, keep the original diary authoritative until the transfer owner checks it.',
                ],
                [
                    'title' => 'Make a release decision',
                    'body' => 'Approve this sample only when the receptionist can distinguish both Rowans, the unknown preference stays intact and both visits match. Record unresolved items individually. In ClipperDesk you can review possible duplicates and invalid client CSV rows. Confirm any assisted transfer of appointments separately before planning a live handover.',
                ],
            ],
            'checklist' => [
                'Keep the source copy under restricted access.',
                'Identify each test person and appointment separately.',
                'Have someone outside the preparation step read back the results.',
            ],
            'limitation' => 'Use fictional details in the rehearsal. Keep live exports and client identifiers out of the worksheet.',
            'feature' => 'client-management',
            'feature_label' => 'Explore ClipperDesk client records',
            'worksheet' => [
                'title' => 'Client move sign-off sheet',
                'instruction' => 'Print one sheet per trial batch. Write the expected result first, then the result you observed.',
                'rows' => [
                    [
                        'title' => 'Identity',
                        'prompt' => 'People expected / found · similar names still separate?',
                    ],
                    [
                        'title' => 'Preferences',
                        'prompt' => 'Missing preference preserved? · fields needing review',
                    ],
                    [
                        'title' => 'Appointments',
                        'prompt' => 'Visits expected / found · details checked',
                    ],
                    [
                        'title' => 'Decision',
                        'prompt' => 'Proceed or hold · unresolved item · owner · next check',
                    ],
                ],
            ],
            'sources_title' => 'Official setup reference',
            'sources' => [
                [
                    'label' => 'Export a client list from Fresha',
                    'href' => 'https://www.fresha.com/help-center/knowledge-base/clients/58-export-your-client-list',
                    'detail' => 'Fresha documents Excel and CSV client-list exports. Its guide also explains why card details cannot be transferred.',
                ],
            ],
            'related_guide' => 'switch-from-paper-bookings',
        ],
        'salon-no-show-response-planner' => [
            'title' => 'Test one change to your no-show routine',
            'seo_title' => 'Salon no-show response planner and tracking sheet | ClipperDesk',
            'description' => 'Compare two booking periods with a printable reminder experiment. Count missed visits, refilled slots and empty time before deciding what to change.',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'topic' => 'Appointment attendance',
            'published_at' => '2026-11-17',
            'reviewed_at' => '2026-11-17',
            'reviewed_by' => 'ClipperDesk editorial team',
            'intro' => 'A missed visit and an empty chair answer different questions. Use this two-period planner to see whether attendance changed and whether the desk managed to refill the time.',
            'sections' => [
                [
                    'title' => 'Choose one change the desk can repeat',
                    'body' => 'For example, add the shop’s correct entrance and change instructions to a reminder. Pick one service group, location and set of weekdays. Keep your normal sending permissions. Write down the change, start date and who will check delivery before counting results.',
                ],
                [
                    'title' => 'Count original appointments consistently',
                    'body' => 'For each period count completed visits and no-shows in the original eligible diary. Add those for the denominator. Keep cancellations, reschedules and replacement visits outside that count. Mark each no-show slot that a replacement actually occupied once. Empty slots equal missed visits minus refilled slots. Use the same cutoff for late arrivals in both periods.',
                ],
                [
                    'title' => 'Compare rates alongside counts',
                    'body' => 'Illustration: 4 misses in 40 eligible visits is 10%. Next period, 3 in 50 is 6%. The difference is 4 percentage points. If two slots were refilled in the first period and none in the second, empty slots actually rose from two to three. If no appointments were eligible, leave the rate unreported.',
                ],
                [
                    'title' => 'Decide what to try next',
                    'body' => 'If fewer clients missed but more chairs sat empty, review the refill process separately. If reminder delivery failed, fix delivery before judging the wording. Record holidays, staff changes or different service mixes. A short comparison can guide the next test without establishing what caused the difference.',
                ],
            ],
            'checklist' => [
                'Use comparable weekdays.',
                'Count every original visit once.',
                'Separate attendance from refilling.',
                'Set a review date before changing the routine.',
            ],
            'limitation' => 'Keep only aggregate counts. Use existing client communication preferences when testing reminders.',
            'feature' => 'online-booking',
            'feature_label' => 'Explore online booking in ClipperDesk',
            'worksheet' => [
                'title' => 'Two-period attendance log',
                'instruction' => 'Use one line per period. Missed rate = missed ÷ eligible × 100. Record counts even for a small sample.',
                'rows' => [
                    [
                        'title' => 'Change being tested',
                        'prompt' => 'Change · service group · dates · owner',
                    ],
                    [
                        'title' => 'Before',
                        'prompt' => 'Eligible ___   Missed ___   Refilled ___   Empty ___   Missed rate ___',
                    ],
                    [
                        'title' => 'After',
                        'prompt' => 'Eligible ___   Missed ___   Refilled ___   Empty ___   Missed rate ___',
                    ],
                    [
                        'title' => 'Review',
                        'prompt' => 'What else changed? · keep, revise or repeat · review date',
                    ],
                ],
            ],
            'sources_title' => 'Official setup reference',
            'sources' => [
                [
                    'label' => 'Square US appointment settings',
                    'href' => 'https://squareup.com/help/us/en/article/5351-manage-your-square-appointments-account-settings',
                    'detail' => 'Use the communication settings section to locate your appointment confirmation and reminder preferences.',
                ],
            ],
        ],
        'salon-booking-slot-exercise' => [
            'title' => 'Which appointment time can you offer?',
            'seo_title' => 'Salon booking slot exercise with worked answers | ClipperDesk',
            'description' => 'Practise fitting a salon service and cleanup around a break, an existing booking and closing time. Reveal the answers and print a desk worksheet.',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'topic' => 'Front desk practice',
            'published_at' => '2026-11-17',
            'reviewed_at' => '2026-11-17',
            'reviewed_by' => 'ClipperDesk editorial team',
            'intro' => 'One stylist, one chair, three possible start times. The service takes 35 minutes and cleanup takes 10. Choose the times you could offer before opening the answer.',
            'sections' => [
                [
                    'title' => 'Read the diary',
                    'body' => 'On this fictional shift the stylist works 12:00–15:00. Lunch is 12:45–13:15. An existing appointment occupies the chair from 14:00 to 14:30, including cleanup. Starts are offered every 15 minutes. Assume no other restrictions. A booking may finish exactly when the next commitment begins.',
                ],
                [
                    'title' => 'Try three start times',
                    'body' => 'A client asks about 12:15, 13:15 or 14:30. For each start, add the service and cleanup, then compare the full occupied interval with lunch, the next booking and closing. Write your choice on the sheet before revealing the answer.',
                ],
                [
                    'title' => 'Repeat with your own diary',
                    'body' => 'Ask a colleague to change just one constraint. For example, if cleanup becomes 15 minutes, the 13:15 start now occupies the chair until 14:05 and conflicts with the booked client. For a real offer, check the qualified professional and any required room or equipment in the live diary.',
                ],
            ],
            'checklist' => [
                'Check the whole visit plus cleanup.',
                'Check the professional and chair.',
                'Check closing and the next booking before promising a start.',
            ],
            'limitation' => 'This exercise uses a fictional diary. Confirm live availability before offering a client a time.',
            'feature' => 'online-booking',
            'feature_label' => 'See how ClipperDesk handles online booking',
            'worksheet' => [
                'title' => 'Available-slot practice sheet',
                'instruction' => 'Write down the first conflict or mark the time available. Cover the worked answer when sharing the exercise.',
                'rows' => [
                    [
                        'title' => '12:15 start',
                        'prompt' => 'Service ends ___   Cleanup ends ___   Offer? ___   Reason',
                    ],
                    [
                        'title' => '13:15 start',
                        'prompt' => 'Service ends ___   Cleanup ends ___   Offer? ___   Reason',
                    ],
                    [
                        'title' => '14:30 start',
                        'prompt' => 'Service ends ___   Cleanup ends ___   Offer? ___   Reason',
                    ],
                ],
            ],
            'sources_title' => 'Official setup reference',
            'sources' => [
                [
                    'label' => 'Square US personal events guide',
                    'href' => 'https://squareup.com/help/us/en/article/5533-create-a-personal-event-on-your-square-appointments-calendar',
                    'detail' => 'When recreating a break in Square, check the Block time option for the personal event.',
                ],
            ],
            'related_guide' => 'service-timing-and-buffers',
            'answer' => '12:15 ends at 12:50 and cleanup ends at 13:00, crossing lunch. 13:15 ends at 13:50 and cleanup ends at 14:00, exactly when the occupied slot begins: offer 13:15. At 14:30 the service ends at 15:05 and cleanup ends at 15:15, after closing.',
        ],
        'barber-walk-in-handover-sheet' => [
            'title' => 'Track what happens after a walk-in handover',
            'seo_title' => 'Barber walk-in handover observation sheet | ClipperDesk',
            'description' => 'Print a shift observation sheet to track walk-in offers, handovers and changes to booked visits. Includes a repeatable method and a fictional example.',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'topic' => 'Walk-in queue',
            'published_at' => '2026-11-17',
            'reviewed_at' => '2026-11-17',
            'reviewed_by' => 'ClipperDesk editorial team',
            'intro' => 'When one receptionist quotes a wait and another takes over, can the next person keep the promise? Use this observation sheet for one agreed shift and review the gaps with your team.',
            'sections' => [
                [
                    'title' => 'Agree what counts',
                    'body' => 'Choose one desk, one observation window and a willing team. Include every new walk-in request during the window, even when the person leaves without an offer. Assign a sequence number. Record one row per request. A handover means responsibility passed to a different team member before the request was closed.',
                ],
                [
                    'title' => 'Follow the promise across the handover',
                    'body' => 'Record arrival, offered start, whether the offer was accepted and actual start if known. If a booked visit moved, mark yes only when the desk confirms it moved to accommodate this request. Use unknown when you cannot verify the reason. Example only: request 03 arrived 13:05, accepted 13:30, changed hands and started 13:40. The difference from the promised start was ten minutes.',
                ],
                [
                    'title' => 'Close the shift with the team',
                    'body' => 'Count total requests, requests handed over and accepted offers with both start times recorded. For that last group, subtract offered start from actual start; negative values mean early. Count unknown outcomes separately. If two of six handovers lack a recorded start, report the four observed outcomes and both gaps. Ask which single piece of information would have helped the next person.',
                ],
                [
                    'title' => 'Compare another shift fairly',
                    'body' => 'Repeat during comparable hours with the same definitions. Keep shop, staffing level and date range attached to any totals you share. A shift sheet describes that desk during that window. Broader findings require participating shops, permission to publish, sample sizes and an account of missing observations.',
                ],
            ],
            'checklist' => [
                'Agree on the window and who is observing.',
                'Include declined offers and unknown outcomes.',
                'Review a handover with the person receiving it.',
            ],
            'limitation' => 'Use sequence numbers only. Keep client names, contact details and staff performance notes off the sheet.',
            'feature' => 'calendar-and-walk-ins',
            'feature_label' => 'See the ClipperDesk walk-in queue',
            'worksheet' => [
                'title' => 'Walk-in observation sheet',
                'instruction' => 'Print extra sheets for busy shifts. Enter local times. Use a dash for no offer and a question mark for an unknown result.',
                'rows' => [
                    [
                        'title' => 'Shift',
                        'prompt' => 'Date · location code · window · team size',
                    ],
                    [
                        'title' => 'Request 1',
                        'prompt' => 'Arrived ___   Offered ___   Accepted? ___   Handover? ___   Started ___   Booked visit moved? ___',
                    ],
                    [
                        'title' => 'Request 2',
                        'prompt' => 'Arrived ___   Offered ___   Accepted? ___   Handover? ___   Started ___   Booked visit moved? ___',
                    ],
                    [
                        'title' => 'Request 3',
                        'prompt' => 'Arrived ___   Offered ___   Accepted? ___   Handover? ___   Started ___   Booked visit moved? ___',
                    ],
                    [
                        'title' => 'Request 4',
                        'prompt' => 'Arrived ___   Offered ___   Accepted? ___   Handover? ___   Started ___   Booked visit moved? ___',
                    ],
                    [
                        'title' => 'Shift review',
                        'prompt' => 'Total requests ___   Handovers ___   Unknown outcomes ___   Next change',
                    ],
                ],
            ],
            'sources_title' => 'Official setup reference',
            'sources' => [
                [
                    'label' => 'Fresha blocked time guide',
                    'href' => 'https://www.fresha.com/help-center/knowledge-base/calendar/18-set-up-and-manage-blocked-time',
                    'detail' => 'If a gap was unavailable, check how the desk records breaks and other blocked time before interpreting the wait.',
                ],
            ],
        ],
        'service-timing-and-buffers' => [
            'related_guide' => 'salon-booking-slot-exercise',
            'seo_title' => 'Salon service timing and buffers guide | ClipperDesk',
            'image' => '/images/marketing/editorial/product-workday.webp',
            'title' => 'Set salon service times that work in a real day',
            'description' => 'Plan salon service lengths, processing and cleanup time, staff availability and breaks with a worked example and a printable booking checklist.',
            'topic' => 'Service planning', 'published_at' => '2026-11-15', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-11-15',
            'intro' => 'A free-looking space in the diary is not always enough for another appointment. Plan the full service, the people who can deliver it and the time needed to prepare for the next client. This guide helps salon and barbershop teams prepare their service menu before sharing online booking; it is separate from deciding how to fit an arriving walk-in into today’s queue.',
            'sections' => [
                ['title' => 'Start with the service you actually deliver', 'body' => 'Choose one specific service and record what is included. A short maintenance cut and a first consultation may need different amounts of time. Review recent visits with the people doing the work rather than copying a competitor’s duration. Note the planned time, the actual finish and the reason for a difference. Do not record unnecessary client details in a timing worksheet.'],
                ['title' => 'Separate hands-on, processing and cleanup time', 'body' => 'Hands-on work needs the professional’s attention. Processing time is part of the client visit and may still need supervision or a resource. Cleanup prepares the space for the next client. In ClipperDesk, the visit includes active and processing time; the calendar span also includes cleanup. Do not assume processing frees every staff member, chair or room. Check the service’s saved timing and allocation before offering another visit.'],
                ['title' => 'Check who can perform the whole service', 'body' => 'Make the service available only with professionals who are qualified and scheduled to deliver it. Review any staff-specific duration and the working location. A professional being present does not mean every service is available with them. If a visit involves more than one person, check each handoff and the complete sequence rather than treating one free starting time as a promise that the whole visit will fit.'],
                ['title' => 'Keep breaks and opening hours separate from duration', 'body' => 'Service length answers how long the work occupies time. The booking start interval answers which start times clients may choose; it does not shorten the service. Staff shifts, breaks, leave and location hours also limit the available window. Test a booking that ends at a break or closing boundary and one that would run beyond it. Do not remove a real break to make an otherwise impossible slot appear available.'],
                ['title' => 'Work through a realistic timing example', 'body' => 'Illustrative example: a haircut starts at 10:00, takes 40 minutes of active work and needs 10 minutes of cleanup. With no processing time, the client visit ends at 10:40 and the calendar span ends at 10:50. A second visit cannot use the same occupied professional or resource at 10:40. If a break begins at 10:45, review a different start, a shorter genuinely suitable service or another eligible person. These numbers explain the method; use your own timings and preview the actual rules.'],
                ['title' => 'Preview the last possible appointment on a phone', 'body' => 'Save the service and staff availability, then preview the public booking page with fictional client details. Confirm the displayed service, price, professional, date, local time and location. Try the last slot before closing and a slot beside a break. Review the calendar after confirmation. If no suitable slot exists, change the real schedule or service setup and test again; repeatedly refreshing the same unavailable slot will not create time.'],
                ['title' => 'Review timings without moving existing promises silently', 'body' => 'After a short, defined review period, compare planned and actual finishes for the same service and operating conditions. Look for a recurring cause before changing the menu. Review the effect on future availability and already-booked visits through the existing change process. A new duration should not be presented as permission to change a client’s reserved time without checking the affected appointments and communicating any agreed change.'],
            ],
            'checklist' => ['Service name and what is included agreed', 'Active, processing and cleanup minutes checked', 'Eligible staff and working location checked', 'Required chair, room or equipment considered', 'Start interval distinguished from service duration', 'Break and closing-boundary examples tested', 'Phone preview and calendar result agree', 'Owner and date for the timing review recorded'],
            'limitation' => 'The timings above are illustrative. Follow the safety and qualification rules for your services. A timing worksheet does not reserve availability or change an existing appointment.',
            'feature' => 'online-booking',
        ],
        'switch-from-paper-bookings' => [
            'related_guide' => 'salon-client-migration-rehearsal',
            'seo_title' => 'Move salon bookings from paper to software | ClipperDesk',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'title' => 'Move from a paper salon diary without losing bookings',
            'description' => 'Prepare client records, future appointments and a clear handover when moving salon bookings from paper or spreadsheets, with a printable changeover checklist.',
            'topic' => 'Changing booking tools', 'published_at' => '2026-11-15', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-11-15',
            'intro' => 'Changing tools should leave the team with one reliable diary and clients with the appointment they expect. Plan the handover before copying records or sharing a new booking link. This guide covers the operating change; it does not promise that every field or historical record from another system can be imported.',
            'sections' => [
                ['title' => 'Choose a handover owner and a single working diary', 'body' => 'Name the person who checks the changeover and the date, time and location where the new diary becomes authoritative. Until that point, record every new booking, cancellation and change in the agreed current diary. Keep a controlled list of changes made while preparing the new one. Running two independent booking channels without a clear owner can reserve the same time twice.'],
                ['title' => 'Inventory only the records you need', 'body' => 'List clients, future appointments, services, professionals, locations and any important appointment instructions. Keep a secure source copy and limit access to the people doing the work. Separate current operational records from older material that needs a retention decision. Do not put client contact details, consultation notes or private appointment links into an ordinary support email to explain the migration.'],
                ['title' => 'Prepare client details and review possible duplicates', 'body' => 'Agree a consistent format for names, contact fields and any fields supported by the client CSV import. Check missing values, duplicate rows and records belonging to the same person before importing. In ClipperDesk, client CSV imports let you review invalid rows and possible duplicates. Do not assume that identical names are the same person or merge profiles just to reduce a row count. Keep the source copy so corrections remain understandable.'],
                ['title' => 'Treat future appointments as a separate check', 'body' => 'A client record is not an appointment. For every future visit, confirm the local date and time zone, service, professional, duration, location and current booking status. Discuss assisted transfer of upcoming appointments with the ClipperDesk team and agree supported fields, timing and any costs first. Do not describe this as a universal self-service appointment import. Payment amounts and past receipts need their own reviewed handling; copying a note does not recreate financial history.'],
                ['title' => 'Compare a small representative batch before the whole handover', 'body' => 'Illustrative review batch: one returning client, one possible duplicate, one long service, one visit beside a staff break and one recently changed appointment. Compare each source record with the prepared result. Check fields and permissions with the person who will use them. If a required field or visit cannot be represented correctly, stop that part of the transfer and agree a supported approach before copying more records.'],
                ['title' => 'Test booking, changes and team access', 'body' => 'Use fictional client details to test the public booking link on a phone. Confirm that services, staff, opening hours and breaks are correct. Check an appointment change and the result in the calendar, then review a normal staff member’s access to client records. Keep public booking closed until the owner has reviewed the setup. Test records should remain clearly identified and should not be counted as customer acquisition.'],
                ['title' => 'Finish the handover and keep a useful fallback', 'body' => 'At the agreed changeover time, review the controlled list of intervening changes, compare future booking counts and resolve each difference. Share the new link only when the working diary is accurate. Keep the old source securely available according to the agreed retention rules, with a named person handling exceptions. If the handover is delayed, keep the agreed current diary authoritative and tell the team which link to use; avoid asking clients to rebook blindly.'],
            ],
            'checklist' => ['Handover owner, local date and time agreed', 'One authoritative diary identified', 'Source copy secured with restricted access', 'Client fields and duplicate review completed', 'Future visits checked separately from client records', 'Supported transfer scope, timing and costs agreed', 'Representative records and phone booking tested', 'Changes during preparation checked before launch', 'Staff know the correct link and exception contact'],
            'limitation' => 'Import and assisted migration depend on the source records and supported fields. Review scope and costs with the team before transferring personal information. No universal compatibility, automatic payment-history transfer or fixed turnaround is promised.',
            'feature' => 'client-management',
        ],
        'daily-operations-checklist' => [
            'seo_title' => 'Daily opening and closing checklist | ClipperDesk',
            'image' => '/images/marketing/editorial/product-workday.webp',
            'title' => 'Daily opening and closing checklist',
            'description' => 'A free printable daily checklist for appointment-led teams: staff hours, bookings, walk-ins, client handoffs, received payments and closing checks.',
            'topic' => 'Daily operations', 'published_at' => '2026-11-05', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-11-05',
            'intro' => 'Keep this checklist beside the front desk in a salon, barbershop, studio, spa or other appointment-led business. Assign each check to a person, use your local working date and adapt it to the services and safety rules that apply to you.',
            'sections' => [
                ['title' => 'Before opening: confirm people and time', 'body' => 'Check the working location, staff hours, breaks and absences. Review long services, handoffs and any required rooms or equipment. Resolve conflicting plans before promising an appointment or a walk-in start time.'],
                ['title' => 'Before the first visit: check the booking information', 'body' => 'Review changes and cancellations since the previous shift. Confirm service, professional, time and location. Check whether confirmations or reminders failed, and use the appropriate contact process while respecting client preferences.'],
                ['title' => 'During service: keep the working plan current', 'body' => 'Record arrivals, service progress and appointment changes. Before fitting in a walk-in, check that the whole service fits around reserved time. Explain waiting-time changes promptly and keep relevant client preferences available only to the right people.'],
                ['title' => 'At checkout: explain and record the sale', 'body' => 'Confirm delivered services, products, tax, discounts, tips and any payment already received. Record the method and amount actually collected, issue the receipt and explain any unpaid balance. Use your external payment service for card charges or card refunds.'],
                ['title' => 'Before closing: leave a clear handoff', 'body' => 'Check completed visits against saved sales, receipts and payment records. Investigate differences rather than changing totals to make them match. List unpaid balances and unresolved tasks, then review tomorrow’s first appointments and assign follow-up work.'],
            ],
            'checklist' => ['Working date, location and person responsible', 'Staff hours, breaks, absences and service eligibility checked', 'Long visits, handoffs and room needs reviewed', 'Changes, cancellations and message failures reviewed', 'Walk-ins fit around reserved appointments', 'Relevant client notes available to the right team members', 'Delivered items and received payments recorded', 'Receipts and unpaid balances checked', 'Closing differences and follow-up owner recorded', 'Tomorrow’s exceptions handed over'],
            'limitation' => 'Adapt this operating checklist to your business. It does not replace local safety, licensing, accounting or clinical procedures. ClipperDesk records payments received elsewhere and does not collect deposits online.',
            'feature' => 'calendar-and-walk-ins',
        ],
        'booking-policy-basics' => [
            'seo_title' => 'Booking policy guide for service businesses | ClipperDesk',
            'image' => '/images/marketing/editorial/resources-planning.webp',
            'title' => 'Set a clear booking policy',
            'description' => 'Set booking, deposit and cancellation terms your clients can understand and your team can apply consistently.',
            'topic' => 'Booking policy', 'published_at' => '2026-08-16', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-10-13',
            'intro' => 'A useful booking policy tells clients what is reserved, what may be charged, when changes are allowed and how exceptions are handled. It should match the team’s actual operating process and receive appropriate legal review.',
            'sections' => [
                ['title' => 'Start with the operating decision', 'body' => 'List which services, staff or lead times need special handling. Avoid copying a stricter policy that the team cannot apply consistently.'],
                ['title' => 'Put terms before confirmation', 'body' => 'Show deposit amount, cancellation window and important exceptions before the client confirms—not only in a later reminder.'],
                ['title' => 'Preserve the accepted version', 'body' => 'Keep the policy version and calculation attached to the appointment so a later edit does not rewrite an earlier agreement.'],
                ['title' => 'Design the exception path', 'body' => 'Define who may waive a deposit or policy warning, which reason is recorded and how a refund or checkout correction remains traceable.'],
            ],
            'checklist' => ['Services and circumstances covered', 'Deposit calculation and due point', 'Cancellation/reschedule window', 'Late/no-show handling', 'Refund/waiver authority and reason', 'Client-visible contact or recovery path', 'Legal and operations approval date'],
            'limitation' => 'This guide is general operating guidance, not legal advice. Online deposit collection is not currently available in ClipperDesk.',
            'feature' => 'online-booking',
        ],
        'salon-opening-checklist' => [
            'seo_title' => 'Service business opening checklist | ClipperDesk',
            'image' => '/images/marketing/industries/independent-stylist.webp',
            'title' => 'Prepare your business for online booking',
            'description' => 'Prepare the minimum business, location, service, staff and booking rules needed before sharing an online booking link.',
            'topic' => 'Business setup', 'published_at' => '2026-08-16', 'reviewed_by' => 'ClipperDesk editorial team', 'reviewed_at' => '2026-10-13',
            'intro' => 'Complete the required setup before accepting appointments. Add the details your calendar and clients need first.',
            'sections' => [
                ['title' => 'Set your business details and opening hours', 'body' => 'Confirm display name, time zone, currency, address/contact context and the local opening intervals that determine bookable time.'],
                ['title' => 'Make services schedulable', 'body' => 'Give each service an accurate duration, price, eligible staff and any processing, cleanup, add-on or physical-resource requirement.'],
                ['title' => 'Make staff availability explicit', 'body' => 'Record each person’s location assignment, working intervals, breaks and service eligibility before relying on public availability.'],
                ['title' => 'Preview before publishing', 'body' => 'Check your policy, booking questions and available services in the preview, then publish your page when setup is complete.'],
            ],
            'checklist' => ['Business name, time zone and currency', 'Location details and weekly hours', 'Service duration, price and eligibility', 'Staff assignments, hours and breaks', 'Resource quantities where needed', 'Booking/deposit/cancellation policy', 'Preview and check required setup'],
            'limitation' => 'This checklist does not replace tax, employment, licensing, safety, insurance or privacy advice for a specific service business.',
            'feature' => 'calendar-and-walk-ins',
        ],
    ],
];
