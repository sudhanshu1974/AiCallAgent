-- migration_script.sql

-- Drop tables if they exist
IF OBJECT_ID('call_details', 'U') IS NOT NULL
    DROP TABLE call_details;
IF OBJECT_ID('calls', 'U') IS NOT NULL
    DROP TABLE calls;
IF OBJECT_ID('leads', 'U') IS NOT NULL
    DROP TABLE leads;

-- Create 'leads' table
CREATE TABLE leads (
    leadsid INT PRIMARY KEY IDENTITY(1,1),
    first_name NVARCHAR(MAX),
    last_name NVARCHAR(MAX),
    phone_number NVARCHAR(MAX),
    title NVARCHAR(MAX),
    company NVARCHAR(MAX),
    company_name_for_emails NVARCHAR(MAX),
    email NVARCHAR(MAX),
    status NVARCHAR(MAX),
    seniority NVARCHAR(MAX),
    departments NVARCHAR(MAX),
    industry NVARCHAR(MAX),
    person_linkedin_url NVARCHAR(MAX),
    website NVARCHAR(MAX),
    company_linkedin_url NVARCHAR(MAX),
    country NVARCHAR(MAX),
    company_address NVARCHAR(MAX),
    company_city NVARCHAR(MAX),
    company_state NVARCHAR(MAX),
    company_country NVARCHAR(MAX),
    subsidiary_of NVARCHAR(MAX),
    email_sent INT DEFAULT 0,
    email_open INT DEFAULT 0,
    email_bounced INT DEFAULT 0,
    replied INT DEFAULT 0
);

-- Create 'calls' table
CREATE TABLE calls (
    callid INT PRIMARY KEY IDENTITY(1,1),
    leadsid INT,
    callscript NVARCHAR(MAX),
    timestamp DATETIME2 DEFAULT GETDATE(),
    status NVARCHAR(MAX),
    FOREIGN KEY (leadsid) REFERENCES leads(leadsid)
);

-- Create 'call_details' table
CREATE TABLE call_details (
    detail_id INT PRIMARY KEY IDENTITY(1,1),
    callid INT,
    user_response NVARCHAR(MAX),
    ai_response NVARCHAR(MAX),
    timestamp DATETIME2 DEFAULT GETDATE(),
    FOREIGN KEY (callid) REFERENCES calls(callid)
);

-- Add index to callid in call_details for performance
CREATE INDEX idx_call_details_callid ON call_details(callid);
