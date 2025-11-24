<?php

// Database configuration
define('DB_NAME', 'voice_agent.db');

// Twilio API credentials
define('TWILIO_SID', getenv('TWILIO_SID') ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '');

// Gemini API credentials
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('BASE_URL', getenv('BASE_URL') ?: '');
