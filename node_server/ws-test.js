// ws-test.js
const WebSocket = require('ws');
const port = 8081;

const wss = new WebSocket.Server({ port }, () => {
  console.log(`WebSocket server listening on ws://localhost:${port}`);
});

wss.on('connection', (ws, req) => {
  console.log('Client connected');
  ws.on('message', (msg) => {
    console.log('Received:', msg.toString());
    ws.send('echo: ' + msg);
  });
  ws.on('close', () => console.log('Client disconnected'));
});