#!/usr/bin/env node
'use strict'

// Kept deliberately tiny: PHP passes the pairing URL via stdin, while this
// script writes a self-contained SVG. No browser JavaScript is involved.
const QRCode = require('../node_modules/qrcode')

let input = ''
process.stdin.setEncoding('utf8')
process.stdin.on('data', (chunk) => { input += chunk })
process.stdin.on('end', async () => {
  try {
    const value = input.trim()
    if (!value) throw new Error('Kein QR-Inhalt übergeben.')
    const svg = await QRCode.toString(value, {
      type: 'svg',
      errorCorrectionLevel: 'M',
      margin: 1,
      color: { dark: '#111827', light: '#ffffff' }
    })
    process.stdout.write(svg)
  } catch (error) {
    process.stderr.write((error && error.message) || 'QR-Code konnte nicht erzeugt werden.')
    process.exitCode = 1
  }
})
