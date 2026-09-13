const editorial = (filename, alt) => ({
    src: `/images/marketing/editorial/${filename}`,
    alt,
});

const industry = (filename, alt) => ({
    src: `/images/marketing/industries/${filename}`,
    alt,
});

export const marketingFamilyVisuals = {
    product: editorial('product-workday.webp', 'A service business manager and professional reviewing the working day together on a tablet.'),
    useCases: editorial('use-cases-front-desk.webp', 'A front desk professional coordinating a busy appointment-led service business.'),
    resources: editorial('resources-planning.webp', 'An independent service business owner planning the week before opening.'),
    pricing: editorial('pricing-decision.webp', 'Two service business partners reviewing an operating plan together.'),
    company: editorial('company-story.webp', 'A diverse group of beauty, wellness and service professionals sharing a natural moment.'),
    security: editorial('security-trust.webp', 'A service business owner reviewing the day at reception while closing the workspace.'),
};

export const featureVisuals = {
    'online-booking': editorial('use-cases-front-desk.webp', 'A front desk professional managing live bookings and arriving clients.'),
    'calendar-and-walk-ins': industry('barbershop.webp', 'A barber serving a client in a modern neighbourhood barbershop.'),
    'client-management': industry('nail-salon.webp', 'A nail professional giving a client focused, personal service.'),
    'checkout-and-reporting': editorial('pricing-decision.webp', 'Service business partners reviewing the numbers behind their working day.'),
};

export const useCaseVisuals = {
    'reduce-scheduling-conflicts': editorial('product-workday.webp', 'A service team reviewing the day together on a tablet.'),
    'manage-walk-ins-and-appointments': industry('barbershop.webp', 'A working barbershop balancing scheduled visits and walk-in demand.'),
    'protect-time-with-deposits': editorial('pricing-decision.webp', 'Business partners discussing the operating rules that protect their time.'),
    'keep-client-history-together': industry('spa-sauna.webp', 'A wellness professional providing a calm, considered client experience.'),
};

export const guideVisuals = {
    'booking-policy-basics': editorial('resources-planning.webp', 'A service business owner writing a clear booking policy before opening.'),
    'salon-opening-checklist': industry('independent-stylist.webp', 'An independent stylist preparing a welcoming studio for the day.'),
};

export const solutionVisuals = {
    barbershops: industry('barbershop.webp', 'A barber giving a precise haircut in a busy modern barbershop.'),
    salons: industry('home-salon-hero.webp', 'A modern salon team serving clients during a busy working day.'),
    'independent-stylists': industry('independent-stylist.webp', 'An independent stylist working with a client in a personal studio.'),
    spas: industry('spa-sauna.webp', 'A refined spa team preparing a calm guest treatment.'),
    'nail-salons': industry('nail-salon.webp', 'A nail professional serving a returning client.'),
    medspas: industry('medspa.webp', 'A medspa professional consulting with a client.'),
    massage: industry('massage.webp', 'A massage professional preparing a considered treatment space.'),
    'fitness-recovery': industry('fitness-recovery.webp', 'A recovery professional guiding an appointment-led session.'),
    'physical-therapy': industry('physical-therapy.webp', 'A physical therapy professional working directly with a client.'),
    'health-practices': industry('health-practice.webp', 'A health practitioner meeting a client in a modern practice.'),
    'tattoo-piercing': industry('tattoo-piercing.webp', 'A tattoo professional preparing for a client appointment.'),
    'pet-grooming': industry('pet-grooming.webp', 'A pet groomer caring for a client in a bright grooming studio.'),
    'tanning-studios': industry('tanning-studio.webp', 'A modern tanning studio prepared for scheduled client visits.'),
};

export const visualFor = (collection, slug, fallback) => collection[slug] ?? fallback;
