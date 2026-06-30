<?php

/**
 * One-time additive restore: link orphan media files to seeded events/feedback rows.
 * Safe to re-run — skips inserts when matching rows already exist.
 *
 * Usage: php scripts/restore_demo_visibility.php
 */

use App\Models\CarbootEvent;
use App\Models\EventImage;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Carbon;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = Carbon::now();

$expectedEvents = [
    1 => 'CMart Weekly Carboot',
    2 => 'CMart Weekly Carboot (Almost Full)',
    3 => 'Changlun Mega Carboot',
];

foreach ($expectedEvents as $id => $title) {
    $event = CarbootEvent::find($id);
    if (!$event || $event->title !== $title) {
        fwrite(STDERR, "Abort: event id {$id} is not \"{$title}\".\n");
        exit(1);
    }
}

$eventFiles = [
    1 => 'events/l0edvuOvsaRzThQidI9tp7oYX1nBqVJ5NA4Z71JN.png',
    2 => 'events/3NprlWswrOV7LUQZD1wCg8aEv5uxuASqiwvFEv4L.png',
    3 => 'events/CIhSuVNZwOHECliOJDFuGNNiCN7KqM1FcKPMPEsQ.jpg',
];

$eventImagesInserted = 0;
foreach ($eventFiles as $eventId => $imagePath) {
    $diskPath = storage_path('app/public/' . $imagePath);
    if (!is_file($diskPath)) {
        fwrite(STDERR, "Warning: missing file {$diskPath}, skipping event {$eventId}.\n");
        continue;
    }

    $exists = EventImage::query()
        ->where('event_id', $eventId)
        ->where('image_path', $imagePath)
        ->exists();

    if ($exists) {
        continue;
    }

    EventImage::create([
        'event_id' => $eventId,
        'image_path' => $imagePath,
        'sort_order' => 0,
        'is_primary' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $eventImagesInserted++;

    CarbootEvent::whereKey($eventId)->update([
        'image_path' => $imagePath,
        'updated_at' => $now,
    ]);
}

$vendor = User::where('email', 'vendor@cmart.com')->first();
$userId = $vendor?->id;

$feedbackMediaDir = storage_path('app/public/feedback_media');
$mediaFiles = [];
if (is_dir($feedbackMediaDir)) {
    foreach (scandir($feedbackMediaDir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        if (preg_match('/\.png$/i', $file) && is_file($feedbackMediaDir . DIRECTORY_SEPARATOR . $file)) {
            $mediaFiles[] = $file;
        }
    }
    sort($mediaFiles);
}

$demoFeedbacks = [
    [
        'comments' => 'Great atmosphere every weekend. Vendors are friendly and parking is easy to find near the main entrance.',
        'reviewer_role' => 'Shopper',
        'rating' => 5,
        'service_rating' => 5,
        'value_rating' => 5,
        'media_index' => 0,
    ],
    [
        'comments' => 'Booking a booth through the portal saved me a lot of time. Staff approval was quick and the process felt smooth.',
        'reviewer_role' => 'Vendor',
        'rating' => 5,
        'service_rating' => 5,
        'value_rating' => 4,
        'media_index' => 1,
    ],
    [
        'comments' => 'Love bringing friends here after class. Plenty of food options and good prices for students on a budget.',
        'reviewer_role' => 'UUM Student',
        'rating' => 4,
        'service_rating' => 4,
        'value_rating' => 5,
        'media_index' => 2,
    ],
    [
        'comments' => 'Our family visits almost every month. Clean layout, helpful security, and a nice community vibe overall.',
        'reviewer_role' => 'Local Resident',
        'rating' => 5,
        'service_rating' => 5,
        'value_rating' => 5,
        'media_index' => 3,
    ],
    [
        'comments' => 'Mega carboot day was well organized with clear signage. Will definitely come again for preloved finds.',
        'reviewer_role' => 'Shopper',
        'rating' => 4,
        'service_rating' => 4,
        'value_rating' => 4,
        'media_index' => 4,
    ],
];

$feedbacksInserted = 0;
foreach ($demoFeedbacks as $index => $demo) {
    $mediaPath = null;
    if (isset($mediaFiles[$demo['media_index']])) {
        $mediaPath = 'feedback_media/' . $mediaFiles[$demo['media_index']];
    }

    $exists = Feedback::query()
        ->where('comments', $demo['comments'])
        ->exists();

    if ($exists) {
        continue;
    }

    Feedback::create([
        'user_id' => $userId,
        'reviewer_role' => $demo['reviewer_role'],
        'comments' => $demo['comments'],
        'rating' => $demo['rating'],
        'service_rating' => $demo['service_rating'],
        'value_rating' => $demo['value_rating'],
        'media_path' => $mediaPath,
        'helpful_count' => 0,
        'is_hidden' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $feedbacksInserted++;
}

echo json_encode([
    'event_images_inserted' => $eventImagesInserted,
    'feedbacks_inserted' => $feedbacksInserted,
    'event_images_total' => EventImage::count(),
    'feedbacks_total' => Feedback::count(),
    'media_files_found' => count($mediaFiles),
], JSON_PRETTY_PRINT) . PHP_EOL;
