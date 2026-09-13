<?php
declare(strict_types=1);

$commonBusiness = [
    ['code' => 'mobile_verification', 'title' => 'Mobile verified', 'description' => 'Your Firebase-verified Partner mobile.', 'requirement' => 'required'],
    ['code' => 'personal', 'title' => 'Personal details', 'description' => 'Add the Partner owner or representative details.', 'requirement' => 'required'],
    ['code' => 'business', 'title' => 'Business details', 'description' => 'Add the official details for your business.', 'requirement' => 'required'],
    ['code' => 'location', 'title' => 'Location', 'description' => 'Confirm where your business operates.', 'requirement' => 'required'],
    ['code' => 'hours', 'title' => 'Business hours', 'description' => 'Set the times customers can reach you.', 'requirement' => 'required'],
    ['code' => 'operations', 'title' => 'Operations', 'description' => 'Configure how your business fulfils customer requests.', 'requirement' => 'required'],
    ['code' => 'documents', 'title' => 'Documents', 'description' => 'Provide documents required for review.', 'requirement' => 'required'],
    ['code' => 'bank', 'title' => 'Bank details', 'description' => 'Prepare settlement account information.', 'requirement' => 'required'],
];

return [
    'RETAIL' => [...$commonBusiness, ['code' => 'retail_setup', 'title' => 'Retail store setup', 'description' => 'Confirm your store category and catalogue onboarding preference.', 'requirement' => 'required']],
    'RESTAURANT' => [...$commonBusiness, ['code' => 'restaurant_setup', 'title' => 'Restaurant setup', 'description' => 'Prepare your menu and restaurant operations.', 'requirement' => 'required']],
    'HOME_SERVICE' => [...$commonBusiness, ['code' => 'home_service_setup', 'title' => 'Services setup', 'description' => 'Prepare the services and availability you offer.', 'requirement' => 'required']],
    'DELIVERY' => [
        ['code' => 'mobile_verification', 'title' => 'Mobile verified', 'description' => 'Your Firebase-verified Partner mobile.', 'requirement' => 'required'],
        ['code' => 'personal', 'title' => 'Personal details', 'description' => 'Add your personal Partner details.', 'requirement' => 'required'],
        ['code' => 'location', 'title' => 'Location', 'description' => 'Confirm your service location.', 'requirement' => 'required'],
        ['code' => 'documents', 'title' => 'Documents', 'description' => 'Provide documents required for review.', 'requirement' => 'required'],
        ['code' => 'bank', 'title' => 'Bank details', 'description' => 'Prepare settlement account information.', 'requirement' => 'required'],
        ['code' => 'delivery_setup', 'title' => 'Delivery setup', 'description' => 'Prepare delivery availability and future vehicle details.', 'requirement' => 'required'],
    ],
];
