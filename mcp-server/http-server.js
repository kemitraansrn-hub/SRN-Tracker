#!/usr/bin/env node
// MCP server (read-only) buat SRN Link Center — versi REMOTE (HTTP), buat
// diakses dari luar lewat tunnel (Cloudflare Tunnel dkk). Token WAJIB
// benar (dicek dari path URL) sebelum request diproses — tanpa token yang
// cocok, request ditolak 403 sebelum nyentuh MCP/database sama sekali.

const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env'), quiet: true });

const http = require('http');
const crypto = require('crypto');
const { McpServer } = require('@modelcontextprotocol/sdk/server/mcp.js');
const { StreamableHTTPServerTransport } = require('@modelcontextprotocol/sdk/server/streamableHttp.js');
const mysql = require('mysql2/promise');
const { registerTools, createPool } = require('./tools');

const TOKEN = process.env.MCP_HTTP_TOKEN;
const PORT = Number(process.env.MCP_HTTP_PORT || 8787);

if (!TOKEN) {
    console.error('MCP_HTTP_TOKEN belum diset di .env — server remote ini gak boleh jalan tanpa token. Berhenti.');
    process.exit(1);
}

const pool = createPool(mysql, process.env);

// Constant-time compare biar gak bocor info lewat timing attack.
function tokenMatches(candidate) {
    const a = Buffer.from(candidate || '');
    const b = Buffer.from(TOKEN);
    if (a.length !== b.length) return false;
    return crypto.timingSafeEqual(a, b);
}

const httpServer = http.createServer(async (req, res) => {
    // URL wajib: /mcp/<token> — token jadi bagian dari alamatnya sendiri,
    // biar yang make (atasan) tinggal paste 1 URL, gak perlu isi field
    // header/auth terpisah.
    const url = new URL(req.url, `http://${req.headers.host}`);
    const parts = url.pathname.split('/').filter(Boolean);

    if (parts[0] !== 'mcp' || !tokenMatches(parts[1])) {
        res.writeHead(403, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Forbidden: token salah atau kosong.' }));
        return;
    }

    if (req.method !== 'POST') {
        res.writeHead(405, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Method tidak didukung, pakai POST.' }));
        return;
    }

    try {
        // Stateless: server + transport baru tiap request, biar gak ada
        // state nyambung antar request/klien yang beda-beda.
        const server = new McpServer({ name: 'srn-link-center', version: '1.0.0' });
        registerTools(server, pool);
        const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });
        res.on('close', () => transport.close());
        await server.connect(transport);
        await transport.handleRequest(req, res);
    } catch (err) {
        console.error('Request error:', err);
        if (!res.headersSent) {
            res.writeHead(500, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Internal server error' }));
        }
    }
});

httpServer.listen(PORT, '127.0.0.1', () => {
    console.log(`MCP HTTP server jalan di http://127.0.0.1:${PORT}/mcp/<token> (cuma localhost, expose lewat tunnel)`);
});
