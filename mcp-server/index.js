#!/usr/bin/env node
// MCP server (read-only) buat SRN Link Center — versi LOKAL (stdio), buat
// dipakai Claude Code / Claude Desktop di mesin yang sama. Lihat
// http-server.js buat versi remote (dengan token, buat diakses dari luar
// lewat tunnel).

const path = require('path');
// quiet:true — dotenv default-nya nge-print "tip" promosi ke stdout, yang
// ngerusak protokol MCP (stdout dipakai khusus buat JSON-RPC di stdio
// transport, gak boleh ada teks lain nyelip).
require('dotenv').config({ path: path.join(__dirname, '..', '.env'), quiet: true });

const { McpServer } = require('@modelcontextprotocol/sdk/server/mcp.js');
const { StdioServerTransport } = require('@modelcontextprotocol/sdk/server/stdio.js');
const mysql = require('mysql2/promise');
const { registerTools, createPool } = require('./tools');

const pool = createPool(mysql, process.env);
const server = new McpServer({ name: 'srn-link-center', version: '1.0.0' });
registerTools(server, pool);

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
}

main().catch((err) => {
    console.error('MCP server gagal start:', err);
    process.exit(1);
});
