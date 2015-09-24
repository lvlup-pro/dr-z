<?php

require __DIR__ . '/src/doctor.php';
$doctor = new Doctor(require 'config.php');
$doctor->get_triggers()->get_hosts()->save_file("result.json");