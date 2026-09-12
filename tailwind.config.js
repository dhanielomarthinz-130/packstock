/** Konfigurasi build Tailwind PackStock — cerminan persis dari konfigurasi inline
 *  yang sebelumnya dipakai Play CDN di includes/header.php. */
module.exports = {
  darkMode: 'class',
  content: [
    './**/*.php',
    './assets/js/**/*.js',
    '!./scratch/**',
  ],
  safelist: [
    // Kelas yang dirakit dinamis di admin.js dan tidak terbaca pemindai.
    { pattern: /^(bg|text|border|ring|from|to)-(rose|blue|emerald|amber|slate|navy|brand|indigo|purple|violet)-(50|100|200|300|400|500|600|700|800|900|950)$/ },
    { pattern: /^(bg|text|border)-(rose|blue|emerald|amber|slate|indigo|purple|violet)-(50|100|500|600|700)\/\d{1,3}$/ },
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50:'#f0fdf4',100:'#dcfce7',200:'#bbf7d0',300:'#86efac',400:'#4ade80',
          500:'#22c55e',600:'#16a34a',700:'#15803d',800:'#166534',900:'#14532d',950:'#052e16'
        },
        blue: {
          50:'#f2f1fb',100:'#e4e3f6',200:'#cacadf',300:'#9d98ca',400:'#6f67b1',
          500:'#3f388f',600:'#262363',700:'#1e1b4f',800:'#17153d',900:'#0e0c27',950:'#080718'
        },
        navy: {
          50:'#f2f1fb',100:'#e4e3f6',200:'#cacadf',300:'#9d98ca',400:'#6f67b1',
          500:'#3f388f',600:'#262363',700:'#1e1b4f',800:'#17153d',900:'#0e0c27',950:'#080718'
        }
      }
    }
  },
  plugins: [],
};
