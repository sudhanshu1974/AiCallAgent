require('dotenv').config();
const express = require('express');
const cors = require('cors');
const twilio = require('twilio');
const mssql = require('mssql');
const { GoogleGenerativeAI } = require('@google/generative-ai');
const WebSocket = require('ws'); // Import WebSocket library
const { URLSearchParams } = require('url'); // Import URLSearchParams for parsing query strings

const app = express();
const PORT = process.env.PORT || 8081; // Changed default to 8081 to match frontend

// --- Clients and Configuration ---
const twilioClient = twilio(process.env.TWILIO_ACCOUNT_SID, process.env.TWILIO_AUTH_TOKEN);
const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY, { apiVersion: 'v1beta' });
const model = genAI.getGenerativeModel({ model: 'gemini-flash-latest' });

const dbConfig = {
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    server: process.env.DB_SERVER,
    database: process.env.DB_DATABASE,
    options: {
        encrypt: false,
        trustServerCertificate: true
    }
};

// --- In-Memory Conversation State Management ---
const callStates = new Map(); // Map to store WebSocket-specific data for each active call

// --- Database Connection ---
let pool;
async function connectDb() {
    try {
        pool = await mssql.connect(dbConfig);
        console.log('Connected to SQL Server database.');
    } catch (err) {
        console.error('Database connection failed:', err);
        process.exit(1);
    }
}

async function getLeadDetails(leadId) {
    if (!pool) {
        console.error('Database not connected.');
        return null;
    }
    try {
        const result = await pool.request()
            .input('leadId', mssql.Int, leadId)
            .query('SELECT Leadsid, first_name, last_name, phone_number FROM leads WHERE Leadsid = @leadId');
        
        if (result.recordset.length > 0) {
            console.log(`Found lead:`, result.recordset[0]);
            return result.recordset[0];
        }
        return null;
    } catch (err) {
        console.error('Error fetching lead details:', err);
        return null;
    }
}

// --- WebSocket Helper Functions ---
function sendTwilioCommand(ws, payload) {
    ws.send(JSON.stringify(payload));
}

function sendAudioToTwilio(ws, audioBase64) {
    sendTwilioCommand(ws, {
        streamSid: ws.streamSid,
        event: 'media',
        media: {
            payload: audioBase64
        }
    });
}

function sendMarkToTwilio(ws, name) {
    sendTwilioCommand(ws, {
        streamSid: ws.streamSid,
        event: 'mark',
        mark: {
            name: name
        }
    });
}

async function playAiResponse(ws, aiResponseText, streamSid) {
    console.log('AI Response:', aiResponseText);
    const currentCallState = callStates.get(ws.callId);
    if (!currentCallState) {
        console.error('No call state found for callId:', ws.callId);
        return;
    }

    currentCallState.isSpeaking = true;
    sendMarkToTwilio(ws, 'ai_speaking_start');

    // TODO: Integrate actual TTS service here
    // For now, we just simulate the speaking duration
    await new Promise(resolve => setTimeout(resolve, aiResponseText.length * 70));
    
    sendMarkToTwilio(ws, 'ai_speaking_end');
    currentCallState.isSpeaking = false;

    // After AI finishes speaking, instruct Twilio to start listening for user's speech again
    sendTwilioCommand(ws, {
        streamSid: streamSid,
        event: 'start',
        start: {
            transcription: {
                stream: true,
                languageCode: 'en-US',
                partialResults: true
            }
        }
    });
}

// --- Middleware Setup ---
const corsOptions = {
  origin: 'http://localhost:8000', // Allow PHP frontend
  optionsSuccessStatus: 200
};
app.use(cors(corsOptions));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use((req, res, next) => {
    console.log(`Incoming Request: ${req.method} ${req.originalUrl}`);
    next();
});

// --- HTTP API Endpoints ---
app.post('/api/make-call', async (req, res) => {
    const { leadId } = req.body;
    if (!leadId) {
        return res.status(400).json({ error: 'Missing leadId' });
    }

    const leadDetails = await getLeadDetails(leadId);
    if (!leadDetails || !leadDetails.phone_number) {
        return res.status(404).json({ error: 'Lead not found or missing phone number.' });
    }

    let callId;
    try {
        const request = pool.request();
        request.input('leadsid', mssql.Int, leadId);
        request.input('callscript', mssql.NVarChar, `Call to ${leadDetails.first_name}`);
        const result = await request.query('INSERT INTO calls (leadsid, callscript) OUTPUT INSERTED.callid VALUES (@leadsid, @callscript)');
        
        if (result.recordset && result.recordset.length > 0) {
            callId = result.recordset[0].callid;
        } else {
            throw new Error('Failed to retrieve callid after insertion.');
        }

        const voiceUrl = `${process.env.BASE_URL}/api/voice/twiml-connect?leadId=${leadId}&callId=${callId}`;
        
        const call = await twilioClient.calls.create({
            url: voiceUrl,
            to: leadDetails.phone_number,
            from: process.env.TWILIO_PHONE_NUMBER
        });

        console.log(`Call initiated: ${call.sid} to ${leadDetails.phone_number}`);
        res.status(200).json({ message: 'Call initiated successfully', callSid: call.sid, callId: callId });

    } catch (error) {
        console.error('Error initiating call or saving to DB:', error);
        res.status(500).json({ error: 'Failed to initiate call or save call record' });
    }
});

app.post('/api/voice/twiml-connect', (req, res) => {
    const { leadId, callId } = req.query;
    const twiml = new twilio.twiml.VoiceResponse();

    if (!process.env.BASE_URL) {
        console.error('BASE_URL environment variable is not set. Cannot generate WebSocket URL.');
        twiml.say('I am sorry, the system is not configured correctly. Goodbye.');
        twiml.hangup();
        res.type('text/xml');
        return res.send(twiml.toString());
    }

    const wsUrl = `${process.env.BASE_URL.replace('https', 'wss')}/media?leadId=${leadId}&callId=${callId}`;
    console.log(`Generated WebSocket URL for Twilio: ${wsUrl}`);

    twiml.say('Please wait while I connect you.');
    twiml.connect().stream({
        url: wsUrl,
        track: 'inbound_track'
    });

    res.type('text/xml');
    res.send(twiml.toString());
});

app.get('/', (req, res) => {
    res.send('AI Call Agent Node.js Backend is running!');
});

// --- Server and WebSocket Initialization ---
const server = app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
});

const wss = new WebSocket.Server({ server });

wss.on('connection', function connection(ws, req) {
    const url = req.url;
    if (!url.startsWith('/media')) {
        console.log(`Unknown WebSocket connection to ${url}. Terminating.`);
        ws.close(1000, 'Unknown path');
        return;
    }

    console.log(`Twilio Media Stream connected to ${url}`);
    const urlParams = new URLSearchParams(url.split('?')[1]);
    const callId = urlParams.get('callId');
    const leadId = urlParams.get('leadId');
    
    ws.callId = callId;
    ws.leadId = leadId;
    ws.streamSid = null;

    callStates.set(callId, {
        ws, callId, leadId, conversationHistory: [], streamSid: null, isSpeaking: false
    });

    ws.on('message', async function incoming(message) {
        const msg = JSON.parse(message);
        const currentCallState = callStates.get(ws.callId);

        if (!currentCallState && msg.event !== 'start') {
            console.error('No call state found for non-start event. callId:', ws.callId);
            return;
        }

        switch (msg.event) {
            case 'start':
                currentCallState.streamSid = msg.start.streamSid;
                currentCallState.conversationHistory = [
                    { role: 'user', parts: [{ text: 'You are a consultative specialist for Cyber Infrastructure (CIS) named Frank Benz. Your tone is confident, professional, and helpful. Your goal is to book a meeting with a Solution Architect, not to close a sale.' }] }
                ];
                
                try {
                    const request = pool.request();
                    request.input('callId', mssql.Int, ws.callId);
                    request.input('status', mssql.NVarChar, 'in-progress');
                    await request.query('UPDATE calls SET status = @status WHERE callid = @callId');
                } catch (dbError) {
                    console.error(`Error updating call status for ${ws.callId}:`, dbError);
                }
                
                const leadDetails = await getLeadDetails(ws.leadId);
                const leadName = leadDetails ? leadDetails.first_name : 'there';
                const greetingText = `Hi ${leadName}, this is Frank Benz from CIS, a CMMI Level 5 Global Partner. How are you today?`;
                
                console.log('Sending initial greeting:', greetingText);
                await playAiResponse(ws, greetingText, currentCallState.streamSid);
                break;

            case 'media':
                // This is where you would forward audio to a different STT service if you weren't using Twilio's integrated one.
                // For now, we do nothing here as Twilio handles it.
                break;

            case 'stop':
                console.log('Twilio Stop Event');
                try {
                    const request = pool.request();
                    request.input('callId', mssql.Int, ws.callId);
                    request.input('status', mssql.NVarChar, 'completed');
                    await request.query('UPDATE calls SET status = @status WHERE callid = @callId');
                } catch (dbError) {
                    console.error(`Error updating call status for ${ws.callId}:`, dbError);
                }
                callStates.delete(ws.callId);
                break;

            case 'mark':
                console.log('Twilio Mark Event:', msg.mark.name);
                break;

            case 'speech_recognition_alternative':
                const transcript = msg.speech_recognition_alternative.transcript;
                console.log('Twilio STT Result:', transcript);

                if (currentCallState.isSpeaking) {
                    console.log('User interrupted while AI was speaking. Ignoring.');
                    return; // Simple barge-in handling
                }

                currentCallState.conversationHistory.push({ role: 'user', parts: [{ text: transcript }] });

                try {
                    const fullUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${process.env.GEMINI_API_KEY}`;
                    const apiResponse = await fetch(fullUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: currentCallState.conversationHistory })
                    });

                    if (!apiResponse.ok) throw new Error(`Google API failed with status ${apiResponse.status}`);

                    const responseData = await apiResponse.json();
                    const aiResponse = responseData.candidates[0].content.parts[0].text;

                    currentCallState.conversationHistory.push({ role: 'model', parts: [{ text: aiResponse }] });
                    
                    try {
                        const request = pool.request();
                        request.input('callId', mssql.Int, ws.callId);
                        request.input('user_response', mssql.NVarChar, transcript);
                        request.input('ai_response', mssql.NVarChar, aiResponse);
                        request.input('timestamp', mssql.DateTime, new Date());
                        await request.query('INSERT INTO call_details (callid, user_response, ai_response, timestamp) VALUES (@callId, @user_response, @ai_response, @timestamp)');
                    } catch (dbError) {
                        console.error(`Error saving conversation turn for callId ${ws.callId}:`, dbError);
                    }

                    await playAiResponse(ws, aiResponse, currentCallState.streamSid);

                } catch (error) {
                    console.error('Error with Gemini AI or processing:', error);
                    await playAiResponse(ws, "I seem to be having some technical difficulties. Please call back later.", currentCallState.streamSid);
                }
                break;
        }
    });

    ws.on('close', () => {
        console.log('Twilio Media Stream Disconnected');
        if (ws.callId) {
            callStates.delete(ws.callId);
        }
    });

    ws.on('error', (error) => {
        console.error('WebSocket Error for /media:', error);
    });
});

// --- Server Start ---
connectDb().then(() => {
    console.log('Database connection successful. Starting server...');
}).catch(error => {
    console.error("Failed to connect to the database. Server not started.", error);
    process.exit(1);
});

process.on('SIGTERM', async () => {
    console.log('SIGTERM received. Closing server and DB pool.');
    if (pool) await pool.close();
    server.close(() => {
        console.log('HTTP server closed.');
        process.exit(0);
    });
});