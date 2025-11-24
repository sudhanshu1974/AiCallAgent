<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/Database.php';

function importLeads() {
    try {
        $db = Database::getInstance();
        $csvFile = __DIR__ . '/DB/apollo-contacts-export (1).csv';

        if (!file_exists($csvFile)) {
            echo "CSV file not found.";
            return;
        }

        $file = fopen($csvFile, 'r');
        if (!$file) {
            echo "Failed to open CSV file.";
            return;
        }

        // Get header row and map it
        $header = fgetcsv($file);
        $header = array_map('trim', $header); // Trim whitespace from headers

        $columnMap = [
            'first_name' => array_search('First Name', $header),
            'last_name' => array_search('Last Name', $header),
            'title' => array_search('Title', $header),
            'company' => array_search('Company', $header),
            'company_name_for_emails' => array_search('Company Name for Emails', $header),
            'email' => array_search('Email', $header),
            'status' => array_search('Email Status', $header),
            'seniority' => array_search('Seniority', $header),
            'departments' => array_search('Departments', $header),
            'industry' => array_search('Industry', $header),
            'person_linkedin_url' => array_search('Person Linkedin Url', $header),
            'website' => array_search('Website', $header),
            'company_linkedin_url' => array_search('Company Linkedin Url', $header),
            'country' => array_search('Country', $header),
            'company_address' => array_search('Company Address', $header),
            'company_city' => array_search('Company City', $header),
            'company_state' => array_search('Company State', $header),
            'company_country' => array_search('Company Country', $header),
            'subsidiary_of' => array_search('Subsidiary of', $header),
            'email_sent' => array_search('Email Sent', $header),
            'email_open' => array_search('Email Open', $header),
            'email_bounced' => array_search('Email Bounced', $header),
            'replied' => array_search('Replied', $header),
        ];

        $sql = "INSERT INTO leads (
            first_name, last_name, title, company, company_name_for_emails, email, status, seniority,
            departments, industry, person_linkedin_url, website, company_linkedin_url, country,
            company_address, company_city, company_state, company_country, subsidiary_of,
            email_sent, email_open, email_bounced, replied
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $rowCount = 0;
        while (($row = fgetcsv($file)) !== FALSE) {
            $params = [
                $row[$columnMap['first_name']] ?? null,
                $row[$columnMap['last_name']] ?? null,
                $row[$columnMap['title']] ?? null,
                $row[$columnMap['company']] ?? null,
                $row[$columnMap['company_name_for_emails']] ?? null,
                $row[$columnMap['email']] ?? null,
                $row[$columnMap['status']] ?? null,
                $row[$columnMap['seniority']] ?? null,
                $row[$columnMap['departments']] ?? null,
                $row[$columnMap['industry']] ?? null,
                $row[$columnMap['person_linkedin_url']] ?? null,
                $row[$columnMap['website']] ?? null,
                $row[$columnMap['company_linkedin_url']] ?? null,
                $row[$columnMap['country']] ?? null,
                $row[$columnMap['company_address']] ?? null,
                $row[$columnMap['company_city']] ?? null,
                $row[$columnMap['company_state']] ?? null,
                $row[$columnMap['company_country']] ?? null,
                $row[$columnMap['subsidiary_of']] ?? null,
                ($row[$columnMap['email_sent']] === 'true' ? 1 : 0),
                ($row[$columnMap['email_open']] === 'true' ? 1 : 0),
                ($row[$columnMap['email_bounced']] === 'true' ? 1 : 0),
                ($row[$columnMap['replied']] === 'true' ? 1 : 0),
            ];

            try {
                $db->query($sql, $params);
                $rowCount++;
            } catch (Exception $e) {
                echo "Error inserting row: " . $e->getMessage() . "\n";
                // Optionally, log the problematic row data
                // error_log("Failed to insert row: " . print_r($row, true));
            }
        }

        fclose($file);
        echo "Successfully imported $rowCount rows into the leads table.\n";

    } catch (Exception $e) {
        echo 'Database error: ' . $e->getMessage() . "\n";
    }
}

importLeads();

?>
