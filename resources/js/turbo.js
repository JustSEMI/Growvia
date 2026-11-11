// Turbo Drive dimuat sebagai entri tersendiri agar resources/js/app.js tetap
// dapat dimuat langsung oleh harness tes UI (yang menyajikan modul satu per satu
// dan tidak bisa me-resolve specifier npm).
import '@hotwired/turbo';
