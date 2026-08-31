<?php

// Di bagian atas file profile.php kamu nanti:
$userClass = new User($pdo);
$helper = new Helper; // Jika ada fungsi bantuan

$user_data = $userClass->getProfileData($psysuserid);
$access_list = $userClass->getAccessList($psysuserid);
$completion_percentage = $userClass->calculateCompletion($user_data, $photo_url, $primary_email, $primary_bank);
