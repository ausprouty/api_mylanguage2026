<?php

use \App\Services\Database\DatabaseService;

// Database connection
$database = new DatabaseService();

// Array of updates (key => value pairs)
$updates = [
    'D1' => 'money',
    'D2' => 'comparing',
    'D3' => 'reconciliation',
    'D4' => 'relationships',
    'D5' => 'judgment',
    'D6' => 'forgiveness',
    'D7' => 'enemies',
    'D8' => 'conflict',
    'D9' => 'the_poor',
    'D10' => 'what_is_important',
    'D11' => 'good_enough',
    'D12' => 'self',
    'D13' => 'investing_wisely',
    'D14' => 'spoken_words',
    'D15' => 'thankfulness',
    'D16' => 'control',
    'D17' => 'stressed',
    'D18' => 'acceptance',
    'D19' => 'free_speech',
    'D20' => 'wisdom',
    'D21' => 'respond',
    'D22' => 'faith_question',
    'D23' => 'why_am_i_here_question',
    'Q1' => 'about_money_question',
    'Q2' => 'about_comparing_question',
    'Q3' => 'about_reconciliation_question',
    'Q4' => 'about_relationships_question',
    'Q5' => 'about_judgment_question',
    'Q6' => 'about_forgiveness_question',
    'Q7' => 'about_enemies_question',
    'Q8' => 'about_conflict_question',
    'Q9' => 'about_the_poor_question',
    'Q10' => 'about_what_is_important_question',
    'Q11' => 'about_good_enough_question',
    'Q12' => 'about_self_question',
    'Q13' => 'about_investing_wisely_question',
    'Q14' => 'about_spoken_words_question',
    'Q15' => 'about_thankfulness_question',
    'Q16' => 'about_control_question',
    'Q17' => 'about_stress_question',
    'Q18' => 'about_acceptance_question',
    'Q19' => 'about_free_speech_question',
    'Q20' => 'about_wisdom_question',
    'Q21' => 'about_responding_to_god_question',
    'Q22' => 'about_faith_question',
    'Q23' => 'about_why_am_i_here_question',
];

// Prepare and execute updates
foreach ($updates as $key => $value) {
    $column = strpos($key, 'D') === 0 ? 'description_twig_key' : 'question_twig_key';
    $lesson = str_replace(['D', 'Q'], '', $key);

    $query = "UPDATE life_principle_references SET $column = :value WHERE lesson = :lesson";
    $params = array(
        ':value' => $value,
        ':lesson' => $lesson
    );
    $database->executeQuery($query, $params);
}

echo "Updates completed successfully.";
