<?php

declare(strict_types=1);

return [
    'meta' => [
        'home' => ['description' => 'Discover a historical strategy experience for mobile, with living cities, alliances and a shared world.'],
        'features' => ['description' => 'Explore the cities, borders, alliances and campaigns that shape the strategy experience.'],
        'support' => ['description' => 'Find help and contact the support team safely.'],
        'privacy' => ['description' => 'Read how the data needed to operate the service is handled and protected.'],
        'terms' => ['description' => 'Read the rules for responsible service use and the principles of support.'],
    ],
    'nav' => [
        'game' => 'The game', 'support' => 'Support', 'legal' => 'Legal',
        'privacy' => 'Privacy', 'terms' => 'Terms', 'play' => 'Play on mobile',
        'locale' => 'Choose language',
    ],
    'common' => [
        'skip' => 'Skip to main content', 'home' => 'Home', 'language' => 'Language',
        'mobile_only' => 'Made for mobile',
    ],
    'home' => [
        'eyebrow' => 'Strategy for those who think ahead',
        'title' => 'Raise a city. Build an empire.',
        'intro' => 'Make careful decisions, care for your people and find your place in a persistent world of historical strategy.',
        'cta' => 'Explore the game', 'support_cta' => 'Contact support',
        'pillars_title' => 'Every decision leaves a mark',
        'pillars' => [
            ['title' => 'A living city', 'body' => 'Plan buildings, resources and priorities to help your city prosper even while you are away.'],
            ['title' => 'A shared world', 'body' => 'Explore a persistent map, choose your borders and write your story alongside other players.'],
            ['title' => 'Alliances and war', 'body' => 'Negotiate with allies, organise your armies and face conflicts where preparation matters.'],
        ],
        'trust_title' => 'The server owns the outcome',
        'trust_body' => 'Rules, costs and outcomes are computed securely on the server. Your empire stays consistent, whatever device you use.',
        'store_title' => 'Play on mobile',
        'store_body' => 'The complete experience is designed for mobile screens. Store links will be available when a launch is announced.',
    ],
    'features' => [
        'eyebrow' => 'The game world', 'title' => 'Strategy that grows with you',
        'intro' => 'Start with a small city and turn thoughtful choices into lasting influence.',
        'sections' => [
            ['title' => 'Your city', 'body' => 'Balance production, expansion and defence. Every building opens a possibility and creates a new responsibility.'],
            ['title' => 'Your world', 'body' => 'Read the terrain, watch the borders and decide when to explore, cooperate or protect what is yours.'],
            ['title' => 'Your alliance', 'body' => 'Coordinate goals with trusted people, share plans and make diplomacy work in your favour.'],
            ['title' => 'Your war', 'body' => 'Prepare forces, choose the moment and accept that every campaign needs strategy, not just speed.'],
        ],
    ],
    'support' => [
        'eyebrow' => 'We are here to help', 'title' => 'How can we help?',
        'intro' => 'Send a message to the team. Do not include passwords, tokens or account information.',
        'name' => 'Name', 'email' => 'Email', 'subject' => 'Subject', 'message' => 'Message',
        'submit' => 'Send message', 'success' => 'Message received. Our team will review your request.',
        'failure' => 'We could not send this right now. Try again or use the alternate contact below.',
        'alternate' => 'Alternate contact', 'alternate_body' => 'If the form is unavailable, write directly to:',
        'required' => 'Required',
        'validation' => ['unexpected' => 'Unexpected fields were submitted.'],
        'mail' => [
            'intro' => 'New message received from the support form.',
            'name' => 'Name', 'email' => 'Email', 'subject' => 'Subject', 'message' => 'Message',
        ],
    ],
    'privacy' => [
        'eyebrow' => 'Legal information', 'title' => 'Privacy policy', 'updated' => 'Last updated',
        'sections' => [
            ['title' => 'What we collect', 'body' => 'We collect only the data needed to operate the service, answer support requests and keep the platform secure.'],
            ['title' => 'How we use data', 'body' => 'We use this information to provide the service, investigate problems and communicate important changes. We do not sell personal data.'],
            ['title' => 'Your choices', 'body' => 'You can ask about your data and request corrections through the official support channels.'],
        ],
    ],
    'terms' => [
        'eyebrow' => 'Legal information', 'title' => 'Terms of use', 'updated' => 'Last updated',
        'sections' => [
            ['title' => 'Responsible use', 'body' => 'Use the service lawfully, respectfully and consistently with the published rules. Do not try to interfere with the platform.'],
            ['title' => 'Service access', 'body' => 'The service may receive updates, pauses or changes needed for security, maintenance and product development.'],
            ['title' => 'Support', 'body' => 'Support helps with service questions and problems, but never asks for passwords, tokens or other secrets.'],
        ],
    ],
];
