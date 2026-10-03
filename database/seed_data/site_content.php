<?php
/**
 * Website content as it was hardcoded before Step 4, used by
 * database/seed_site_content.php. Settings defaults live in
 * api/site_settings_helper.php (SITE_SETTING_DEFS).
 */
return [
    // Homepage "Smart Packages for Everyday Home Care" cards.
    'home_packages' => [
        [
            'title' => 'AC Care Package', 'label' => 'POPULAR', 'icon' => 'fa-solid fa-snowflake',
            'description' => 'Complete AC inspection, cleaning and routine servicing for better cooling performance.',
            'highlights' => ['AC Inspection', 'Filter Cleaning', 'Cooling Check'],
            'category' => 'ac-service', 'link' => 'ac-service.php', 'featured' => 0,
        ],
        [
            'title' => 'Deep Cleaning Package', 'label' => 'HOME CARE', 'icon' => 'fa-solid fa-broom',
            'description' => 'Professional cleaning support for essential areas of your home in one convenient booking.',
            'highlights' => ['Floor Cleaning', 'Kitchen Cleaning', 'Bathroom Cleaning'],
            'category' => 'home-cleaning', 'link' => 'home-cleaning.php', 'featured' => 0,
        ],
        [
            'title' => 'Kitchen Care Package', 'label' => 'COMPLETE CARE', 'icon' => 'fa-solid fa-kitchen-set',
            'description' => 'A practical combination for kitchen maintenance, chimney cleaning and appliance care.',
            'highlights' => ['Chimney Check', 'Kitchen Cleaning', 'Service Inspection'],
            'category' => 'chimney-repair', 'link' => 'chimney-repair.php', 'featured' => 1,
        ],
        [
            'title' => 'Water Purifier Care', 'label' => 'ESSENTIAL', 'icon' => 'fa-solid fa-droplet',
            'description' => 'Routine RO inspection and purifier servicing for dependable daily use.',
            'highlights' => ['RO Inspection', 'Filter Check', 'Performance Test'],
            'category' => 'water-purifier', 'link' => 'water-purifier.php', 'featured' => 0,
        ],
    ],

    // Category page introduction text (slug => paragraphs).
    'category_long_descriptions' => [
        'ac-service' => [
            'Zen Care Services provides professional air conditioner servicing, repair and maintenance for homes and businesses. Our technicians help keep your AC working efficiently, cooling properly and running smoothly.',
            'Whether you need routine servicing, gas refill, installation or troubleshooting, our team delivers convenient doorstep service with a customer-first approach.',
        ],
        'refrigerator-repair' => [
            'Zen Care Services provides professional refrigerator repair and inspection for single-door, double-door, inverter and side-by-side refrigerators.',
            'From cooling problems and water leakage to ice maker repair, defrosting issues and refrigerator gas refilling, our service professionals provide convenient assistance for common refrigerator problems.',
        ],
        'home-cleaning' => [
            'Zen Care Services provides professional home cleaning for furnished and unfurnished apartments, independent homes and bathrooms.',
            'From routine dusting, vacuuming and mopping to deep cleaning, upholstery care, machine scrubbing and bathroom sanitization, choose the package that matches your home.',
        ],
        'salon' => [
            'Zen Care Services brings professional facial and beauty treatments directly to your home for a convenient and relaxing beauty-care experience.',
            'Choose from brightening facials, anti-tanning treatments, youthful skin care, gold facials, instant glow treatments and professional threading services.',
        ],
        'pest-control' => [
            'Zen Care Services provides professional pest control solutions for homes, apartments and commercial spaces.',
            'Our pest control services are designed to treat cockroaches, ants, bed bugs and other common household pests while covering cracks, drains and difficult-to-reach areas.',
        ],
        'washing-machine-repair' => [
            'Zen Care Services provides professional washing machine inspection, repair, installation and maintenance services for homes. Our experienced technicians diagnose common washing machine problems and provide reliable service.',
            'Whether you have a top load, front load or semi-automatic washing machine, our technicians provide convenient doorstep inspection and service for different models and brands.',
        ],
        'chimney-repair' => [
            'Zen Care Services provides professional chimney inspection, repair, cleaning and maintenance services for modern kitchens. Our technicians help identify suction, motor, fan and control panel problems.',
            'Whether your chimney has poor suction, a faulty motor, blower issue, duct problem or requires installation, our team provides reliable doorstep chimney service.',
        ],
        'water-purifier' => [
            'Zen Care Services provides professional water purifier servicing, filter replacement, repair, installation and maintenance at your doorstep.',
            'Whether your purifier has low water pressure, dispensing issues, pump problems, filter issues or requires regular servicing, our technicians provide reliable and convenient assistance.',
        ],
        'carpenter-service' => [
            'Zen Care Services provides professional carpenter services for everyday household repairs, fittings and installations.',
            'From door lock replacement and curtain rod installation to bed support repair and cupboard hinge fitting, our service professionals help keep your home functional and well maintained.',
        ],
    ],
];
