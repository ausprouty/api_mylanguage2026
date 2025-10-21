<?php

use \App\Services\Database\DatabaseService;

// Database connection
$database = new DatabaseService();

// Array of updates (key => value pairs)
$updates = [
   '1' => 'leaders_call_others_to_follow_christ',
   '2' => 'leaders_teach_attitudes_god_blesses_1',
   '3' => 'leaders_teach_attitudes_god_blesses_2',
   '4' => 'leaders_seek_to_please_god',
   '5' => 'leaders_serve_god',
   '6' => 'leaders_judge_righteously_1',
   '7' => 'leaders_judge_righteously_2',
   '8' => 'leaders_seek_god',
   '9' => 'leaders_obey_god',
   '10' => 'leaders_care_for_outcasts_and_sinners',
   '11' => 'leaders_teach_preach_and_heal',
   '12' => 'leaders_send_people_out',
   '13' => 'leaders_prepare_for_persecution',
   '14' => 'leaders_offer_rest_to_the_weary',
   '15' => 'leaders_teach_about_the_kingdom',
   '16' => 'leaders_accept_the_cost',
   '17' => 'leaders_listen_to_jesus',
   '18' => 'leaders_teach_about_faith',
   '19' => 'leaders_deal_with_sin',
   '20' => 'leaders_honour_marriage',
   '21' => 'leaders_are_servants',
   '22' => 'leaders_meet_needs_with_compassion',
   '23' => 'leaders_invest_faithfully',
   '24' => 'leaders_serve_those_in_need',
   '25' => 'leaders_teach_others_to_obey',
];

// Prepare and execute updates
foreach ($updates as $lesson => $value) {
    // Ensure lesson is treated as an integer (trim spaces, validate, and cast)
    $lesson = intval(trim($lesson));

    if ($lesson > 0) { // Skip invalid or empty lessons
        $query = "UPDATE leadership_references SET description_twig_key = :value WHERE lesson = :lesson";
        $params = [
           ':value' => $value,
           ':lesson' => $lesson,
        ];
        try {
            $database->executeQuery($query, $params);
        } catch (Exception $e) {
            echo "Failed to update lesson $lesson: " . $e->getMessage();
        }
    } else {
        echo "Invalid lesson detected: $lesson\n";
    }
}

echo "Leadership references updated successfully.";
