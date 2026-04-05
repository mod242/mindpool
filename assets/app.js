/**
 * mindpool – Frontend-Logik
 * Enthält: Combobox-Komponente, Tabelle, Filter, Karte, PDF-Export, Toast-System
 */

// === CSRF-Token (wird von PHP in die Seite geschrieben) ===
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

// === SVG-Icons (inline, kein externer Font nötig) ===
const Icons = {
    edit:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>',
    profile: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    pdf:     '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M10 13h4"/><path d="M10 17h4"/></svg>',
    check:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    cross:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    approve: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    merge:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M6 21V9a9 9 0 0 0 9 9"/></svg>',
    trash:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
    remove:  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
};

// === Karten-Marker-Icon ===
const MapMarkerIcon = typeof L !== 'undefined' ? L.icon({
    iconUrl: 'assets/icon.png',
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    popupAnchor: [0, -30],
    tooltipAnchor: [0, -30],
}) : null;

// === Toast-System ===
const Toast = {
    container: null,

    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    show(message, type = 'success', duration = 4000) {
        if (!this.container) this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icon = type === 'success' ? Icons.check : type === 'error' ? Icons.cross : Icons.warning;
        toast.innerHTML = `<span class="toast-icon">${icon}</span><span>${message}</span>`;
        this.container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            toast.style.transition = 'all 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
};

// === API-Hilfsfunktionen ===
async function apiGet(action, params = {}) {
    const url = new URL('api.php', window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
    url.searchParams.set('action', action);
    for (const [k, v] of Object.entries(params)) {
        url.searchParams.set(k, v);
    }
    const resp = await fetch(url);
    if (!resp.ok) {
        const data = await resp.json().catch(() => ({}));
        throw new Error(data.error || `Fehler ${resp.status}`);
    }
    return resp.json();
}

async function apiPost(action, data = {}) {
    const url = new URL('api.php', window.location.origin + window.location.pathname.replace(/[^/]*$/, ''));
    url.searchParams.set('action', action);
    data.csrf_token = CSRF_TOKEN;

    const resp = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN,
        },
        body: JSON.stringify(data),
    });

    const result = await resp.json().catch(() => ({}));
    if (!resp.ok) {
        throw new Error(result.error || result.errors?.join(', ') || `Fehler ${resp.status}`);
    }
    return result;
}

// === Combobox-Komponente ===
class Combobox {
    /**
     * @param {HTMLElement} container - Wrapper-Element
     * @param {Object} options
     * @param {string} options.kategorie - Taxonomie-Kategorie
     * @param {string} options.name - Name des hidden inputs
     * @param {string} options.placeholder - Platzhalter
     * @param {boolean} options.required - Pflichtfeld
     * @param {string} options.value - Initialer Wert
     */
    constructor(container, options = {}) {
        this.container = container;
        this.kategorie = options.kategorie;
        this.name = options.name;
        this.required = options.required || false;
        this.multi = options.multi || false;
        this.eintraege = [];
        this.filtered = [];
        this.highlightIndex = -1;
        this.isOpen = false;
        // Single: string, Multi: array
        if (this.multi) {
            let parsed = [];
            try { parsed = JSON.parse(options.value || '[]'); } catch (e) {}
            this.selectedValues = Array.isArray(parsed) ? parsed : (options.value ? [options.value] : []);
            this.selectedValue = '';
        } else {
            this.selectedValue = options.value || '';
        }

        this.build();
        this.loadEntries();
    }

    build() {
        this.container.classList.add('combobox-wrapper');

        if (this.multi) {
            // Tags-Container für Multi-Select
            this.tagsContainer = document.createElement('div');
            this.tagsContainer.className = 'combobox-tags';
            this.container.appendChild(this.tagsContainer);
            this.renderTags();
        } else {
            // Hidden Input für den eigentlichen Wert (Single)
            this.hiddenInput = document.createElement('input');
            this.hiddenInput.type = 'hidden';
            this.hiddenInput.name = this.name;
            this.hiddenInput.value = this.selectedValue;
            this.container.appendChild(this.hiddenInput);
        }

        // Sichtbares Input
        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'combobox-input';
        this.input.placeholder = this.container.dataset.placeholder || 'Auswählen oder suchen...';
        this.input.autocomplete = 'off';
        this.input.setAttribute('role', 'combobox');
        this.input.setAttribute('aria-expanded', 'false');
        this.input.setAttribute('aria-autocomplete', 'list');
        if (this.required) this.input.setAttribute('aria-required', 'true');
        if (!this.multi) this.input.value = this.selectedValue;
        this.container.appendChild(this.input);

        // Dropdown
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'combobox-dropdown';
        this.dropdown.setAttribute('role', 'listbox');
        this.container.appendChild(this.dropdown);

        // Events
        this.input.addEventListener('focus', () => this.open());
        this.input.addEventListener('input', () => this.onInput());
        this.input.addEventListener('keydown', (e) => this.onKeydown(e));
        document.addEventListener('mousedown', (e) => {
            if (!this.container.contains(e.target)) this.close();
        });
    }

    renderTags() {
        if (!this.tagsContainer) return;
        this.tagsContainer.innerHTML = '';

        // Hidden inputs für Formular-Submit
        this.container.querySelectorAll('input[type="hidden"]').forEach(h => h.remove());
        this.selectedValues.forEach(val => {
            // Tag
            const tag = document.createElement('span');
            tag.className = 'combobox-tag';
            tag.innerHTML = `${val} <button type="button" class="combobox-tag-remove" aria-label="Entfernen">&times;</button>`;
            tag.querySelector('button').addEventListener('click', (e) => {
                e.stopPropagation();
                this.removeValue(val);
            });
            this.tagsContainer.appendChild(tag);

            // Hidden input
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = this.name + '[]';
            hidden.value = val;
            this.container.appendChild(hidden);
        });
    }

    removeValue(val) {
        this.selectedValues = this.selectedValues.filter(v => v !== val);
        this.renderTags();
        this.container.dispatchEvent(new CustomEvent('combobox-select', {
            detail: { values: this.selectedValues }
        }));
    }

    async loadEntries() {
        try {
            this.eintraege = await apiGet('taxonomie_list', { kategorie: this.kategorie });
            this.filtered = [...this.eintraege];
        } catch (err) {
            console.error('Fehler beim Laden der Taxonomie:', err);
        }
    }

    open() {
        this.filter(this.input.value);
        this.isOpen = true;
        this.dropdown.classList.add('open');
        this.input.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.isOpen = false;
        this.dropdown.classList.remove('open');
        this.input.setAttribute('aria-expanded', 'false');
        this.highlightIndex = -1;

        if (this.multi) {
            // Bei Multi: Input leeren nach Close
            this.input.value = '';
        } else {
            // Wenn der eingetippte Wert nicht dem gewählten Wert entspricht
            if (this.input.value !== this.selectedValue) {
                const match = this.eintraege.find(
                    e => e.name.toLowerCase() === this.input.value.toLowerCase()
                );
                if (match) {
                    this.select(match.name);
                } else {
                    this.input.value = this.selectedValue;
                }
            }
        }
    }

    filter(query) {
        const q = query.toLowerCase().trim();
        this.filtered = q
            ? this.eintraege.filter(e => e.name.toLowerCase().includes(q))
            : [...this.eintraege];
        this.render(q);
    }

    onInput() {
        if (!this.multi) {
            this.selectedValue = '';
            this.hiddenInput.value = '';
        }
        this.highlightIndex = -1;
        this.filter(this.input.value);
        if (!this.isOpen) this.open();
    }

    onKeydown(e) {
        if (!this.isOpen) {
            if (e.key === 'ArrowDown' || e.key === 'Enter') {
                this.open();
                e.preventDefault();
            }
            return;
        }

        const items = this.dropdown.querySelectorAll('.combobox-option, .combobox-suggest');
        const maxIndex = items.length - 1;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.highlightIndex = Math.min(this.highlightIndex + 1, maxIndex);
                this.updateHighlight(items);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.highlightIndex = Math.max(this.highlightIndex - 1, 0);
                this.updateHighlight(items);
                break;
            case 'Enter':
                e.preventDefault();
                if (this.highlightIndex >= 0 && items[this.highlightIndex]) {
                    items[this.highlightIndex].click();
                }
                break;
            case 'Escape':
                this.close();
                break;
        }
    }

    updateHighlight(items) {
        items.forEach((item, i) => {
            item.classList.toggle('highlighted', i === this.highlightIndex);
            if (i === this.highlightIndex) {
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    render(query = '') {
        this.dropdown.innerHTML = '';

        if (this.filtered.length === 0 && !query) {
            const noResults = document.createElement('div');
            noResults.className = 'combobox-no-results';
            noResults.textContent = 'Keine Einträge vorhanden';
            this.dropdown.appendChild(noResults);
            return;
        }

        // Einträge anzeigen: Vorschläge oben, dann alphabetisch
        const sorted = [...this.filtered].sort((a, b) => {
            if (a.status === 'vorschlag' && b.status !== 'vorschlag') return -1;
            if (a.status !== 'vorschlag' && b.status === 'vorschlag') return 1;
            return a.name.localeCompare(b.name, 'de');
        });

        sorted.forEach(eintrag => {
            const option = document.createElement('div');
            option.className = 'combobox-option';
            option.setAttribute('role', 'option');
            if (eintrag.status === 'vorschlag') {
                option.classList.add('vorschlag');
            }

            // Multi: bereits gewählte dimmen
            const isSelected = this.multi && this.selectedValues.includes(eintrag.name);
            if (isSelected) option.classList.add('selected');

            let html = eintrag.name;
            if (eintrag.status === 'vorschlag') {
                html += ' <span class="badge-neu">(neu)</span>';
            }
            if (isSelected) html += ' ✓';
            option.innerHTML = html;

            option.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Verhindert Blur/Focus-Wechsel
                e.stopPropagation();
                if (this.multi) {
                    if (isSelected) {
                        this.removeValue(eintrag.name);
                    } else {
                        this.addValue(eintrag.name);
                    }
                    this.input.value = '';
                    this.filter('');
                } else {
                    this.select(eintrag.name);
                    this.close();
                }
            });
            this.dropdown.appendChild(option);
        });

        // "Neu vorschlagen"-Button wenn kein exakter Treffer
        if (query && !this.eintraege.find(e => e.name.toLowerCase() === query.toLowerCase())) {
            const suggest = document.createElement('div');
            suggest.className = 'combobox-suggest';
            suggest.innerHTML = `+ „${query}" neu vorschlagen`;
            suggest.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.suggest(query);
            });
            this.dropdown.appendChild(suggest);
        }
    }

    select(name) {
        if (this.multi) {
            this.addValue(name);
            return;
        }
        this.selectedValue = name;
        this.input.value = name;
        this.hiddenInput.value = name;
        this.input.classList.remove('invalid');
        this.close();

        this.container.dispatchEvent(new CustomEvent('combobox-select', {
            detail: { value: name }
        }));
    }

    addValue(name) {
        if (!this.selectedValues.includes(name)) {
            this.selectedValues.push(name);
            this.renderTags();
            this.input.classList.remove('invalid');
            this.container.dispatchEvent(new CustomEvent('combobox-select', {
                detail: { values: this.selectedValues }
            }));
        }
    }

    async suggest(name) {
        try {
            const result = await apiPost('taxonomie_suggest', {
                kategorie: this.kategorie,
                name: name,
            });

            if (result.existing) {
                // Existierender Eintrag gefunden
                this.select(result.name);
                Toast.show(`„${result.name}" existiert bereits und wurde ausgewählt.`, 'warning');
            } else {
                // Neuer Vorschlag angelegt
                await this.loadEntries();
                this.select(result.name);
                Toast.show(`„${result.name}" wurde als Vorschlag hinzugefügt.`, 'success');
            }
            this.close();
        } catch (err) {
            Toast.show('Fehler beim Vorschlagen: ' + err.message, 'error');
        }
    }

    getValue() {
        return this.multi ? this.selectedValues : this.selectedValue;
    }

    setValue(val) {
        if (this.multi) {
            this.selectedValues = Array.isArray(val) ? val : [val];
            this.renderTags();
        } else {
            this.selectedValue = val;
            this.input.value = val;
            this.hiddenInput.value = val;
        }
    }

    validate() {
        if (this.multi) {
            if (this.required && this.selectedValues.length === 0) {
                this.input.classList.add('invalid');
                return false;
            }
        } else {
            if (this.required && !this.selectedValue) {
                this.input.classList.add('invalid');
                return false;
            }
        }
        this.input.classList.remove('invalid');
        return true;
    }
}

// === Dashboard: Tabelle und Karte ===
const Dashboard = {
    dozenten: [],
    map: null,
    markers: [],
    sortField: 'nachname',
    sortDir: 'asc',

    async init() {
        await this.loadData();
        this.initMap();
        this.renderTable();
        this.bindEvents();
    },

    async loadData() {
        try {
            this.dozenten = await apiGet('list');
        } catch (err) {
            Toast.show('Fehler beim Laden: ' + err.message, 'error');
        }
    },

    initMap() {
        const mapEl = document.getElementById('map');
        if (!mapEl || typeof L === 'undefined') return;

        this.map = L.map('map').setView([51.1657, 10.4515], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 18,
        }).addTo(this.map);

        this.updateMarkers();
    },

    updateMarkers() {
        if (!this.map) return;

        // Bestehende Marker entfernen
        this.markers.forEach(m => m.remove());
        this.markers = [];

        this.dozenten.forEach(d => {
            if (!d.wohnort_lat || !d.wohnort_lng) return;

            const einsatzgebiete = (d.einsatzgebiete || []).join(', ');
            const angebote = (d.angebote || []).join(', ');
            const popup = `
                <strong>${d.vorname} ${d.nachname}</strong><br>
                ${d.wohnort}<br>
                <em>${einsatzgebiete}</em>
                ${angebote ? '<br>' + angebote : ''}
            `;

            const marker = L.marker([d.wohnort_lat, d.wohnort_lng], { icon: MapMarkerIcon })
                .bindTooltip(popup, { direction: 'top' })
                .addTo(this.map);

            marker.on('click', () => {
                // Zur Person in der Tabelle scrollen
                const row = document.querySelector(`tr[data-id="${d.id}"]`);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.style.backgroundColor = 'var(--color-primary-light)';
                    setTimeout(() => row.style.backgroundColor = '', 2000);
                }
            });

            this.markers.push(marker);
        });
    },

    renderTable() {
        const tbody = document.getElementById('dozenten-tbody');
        if (!tbody) return;

        let data = this.getFilteredData();
        data = this.sortData(data);

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="no-data">
                Keine Trainer*innen gefunden.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.map(d => {
            const angeboteTags = (d.angebote || [])
                .map(a => `<span class="badge badge-sm">${a}</span>`)
                .join(' ');
            const wirkung = d.wirkungsfelder
                ? `<div class="text-truncate text-muted-sm">${d.wirkungsfelder}</div>`
                : '';

            const fotoHtml = d.foto_base64 && d.foto_base64.startsWith('data:')
                ? `<img src="${d.foto_base64}" alt="">`
                : d.vorname.charAt(0) + d.nachname.charAt(0);

            return `<tr data-id="${d.id}">
                <td class="foto-cell" data-label="Foto">
                    <div class="trainer-foto">${fotoHtml}</div>
                </td>
                <td data-label="Name"><strong>${d.nachname}, ${d.vorname}</strong></td>
                <td data-label="PLZ">${d.plz || '–'}</td>
                <td data-label="Wohnort">${d.wohnort}${d.land ? ' <span class="text-muted-sm">' + d.land + '</span>' : ''}</td>
                <td data-label="Einsatzgebiet">${(d.einsatzgebiete || []).join(', ') || '–'}</td>
                <td data-label="Wirkungsfelder">${angeboteTags || '–'}${wirkung}</td>
                <td data-label="Aktionen" class="cell-actions">
                    <a href="form.php?id=${d.id}" class="btn-icon" title="Bearbeiten">${Icons.edit}</a>
                    <a href="profile.php?id=${d.id}" class="btn-icon" title="Profil">${Icons.profile}</a>
                    <button class="btn-icon" title="PDF" onclick="Dashboard.exportPDF('${d.id}')">${Icons.pdf}</button>
                </td>
            </tr>`;
        }).join('');
    },

    getFilteredData() {
        let data = [...this.dozenten];

        // Suchfeld
        const search = document.getElementById('search-input')?.value?.toLowerCase() || '';
        if (search) {
            data = data.filter(d => {
                const text = [
                    d.vorname, d.nachname, d.plz, d.wohnort, d.land,
                    ...(d.einsatzgebiete || []),
                    d.aktuelle_taetigkeit, d.wirkungsfelder,
                    ...(d.angebote || []),
                ].join(' ').toLowerCase();
                return text.includes(search);
            });
        }

        // Filter: Einsatzgebiet
        const filterEinsatz = document.getElementById('filter-einsatzgebiet')?.value || '';
        if (filterEinsatz) {
            data = data.filter(d => (d.einsatzgebiete || []).includes(filterEinsatz));
        }

        // Filter: Land
        const filterLand = document.getElementById('filter-land')?.value || '';
        if (filterLand) {
            data = data.filter(d => d.land === filterLand);
        }

        // Filter: Angebot
        const filterAngebot = document.getElementById('filter-angebot')?.value || '';
        if (filterAngebot) {
            data = data.filter(d =>
                (d.angebote || []).includes(filterAngebot)
            );
        }

        return data;
    },

    sortData(data) {
        const dir = this.sortDir === 'asc' ? 1 : -1;
        return data.sort((a, b) => {
            const va = (a[this.sortField] || '').toLowerCase();
            const vb = (b[this.sortField] || '').toLowerCase();
            return va.localeCompare(vb, 'de') * dir;
        });
    },

    setSort(field) {
        if (this.sortField === field) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortField = field;
            this.sortDir = 'asc';
        }

        // Sortier-Indikatoren aktualisieren
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            const indicator = th.querySelector('.sort-indicator');
            if (th.dataset.sort === field) {
                indicator.textContent = this.sortDir === 'asc' ? '▲' : '▼';
            } else {
                indicator.textContent = '↕';
            }
        });

        this.renderTable();
    },

    bindEvents() {
        // Suche
        document.getElementById('search-input')?.addEventListener('input', () => {
            this.renderTable();
        });

        // Filter
        document.getElementById('filter-einsatzgebiet')?.addEventListener('change', () => {
            this.renderTable();
        });
        document.getElementById('filter-land')?.addEventListener('change', () => {
            this.renderTable();
        });
        document.getElementById('filter-angebot')?.addEventListener('change', () => {
            this.renderTable();
        });

        // Sortierung
        document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
            th.addEventListener('click', () => this.setSort(th.dataset.sort));
        });
    },

    async exportPDF(id) {
        // Einzelprofil-PDF → Neues Fenster mit Profil öffnen und drucken
        // oder: jsPDF nutzen
        window.open(`profile.php?id=${id}&pdf=1`, '_blank');
    },

    async exportTablePDF() {
        const filtered = this.getFilteredDozenten();
        if (filtered.length === 0) {
            Toast.show('Keine Daten zum Exportieren.', 'error');
            return;
        }
        await PDFExport.generateTable(filtered);
    },

    exportCSV() {
        const filtered = this.getFilteredDozenten();
        if (filtered.length === 0) {
            Toast.show('Keine Daten zum Exportieren.', 'error');
            return;
        }

        const escape = (val) => {
            const str = String(val ?? '');
            if (str.includes('"') || str.includes(';') || str.includes('\n')) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        };

        const header = ['Name', 'PLZ', 'Wohnort', 'Land', 'Einsatzgebiet', 'Angebote', 'Wirkungsfelder', 'Tätigkeit'];
        const rows = filtered.map(d => {
            return [
                `${d.nachname}, ${d.vorname}`,
                d.plz || '',
                d.wohnort || '',
                d.land || '',
                (d.einsatzgebiete || []).join(', '),
                (d.angebote || []).join(', '),
                d.wirkungsfelder || '',
                d.aktuelle_taetigkeit || '',
            ].map(escape).join(';');
        });

        const bom = '\uFEFF';
        const csv = bom + header.join(';') + '\n' + rows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `mindscool_Trainerpool_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        Toast.show('CSV exportiert.', 'success');
    },

    getFilteredDozenten() {
        const search = document.getElementById('search-input')?.value?.toLowerCase() || '';
        const filterEg = document.getElementById('filter-einsatzgebiet')?.value || '';
        const filterLand = document.getElementById('filter-land')?.value || '';
        const filterAngebot = document.getElementById('filter-angebot')?.value || '';

        return this.dozenten.filter(d => {
            if (filterEg && !(d.einsatzgebiete || []).includes(filterEg)) return false;
            if (filterLand && d.land !== filterLand) return false;
            if (filterAngebot && !(d.angebote || []).includes(filterAngebot)) return false;
            if (search) {
                const haystack = [
                    d.vorname, d.nachname, d.plz, d.wohnort, d.land,
                    ...(d.einsatzgebiete || []),
                    d.aktuelle_taetigkeit, d.wirkungsfelder,
                    d.email, ...(d.angebote || []),
                ].join(' ').toLowerCase();
                if (!haystack.includes(search)) return false;
            }
            return true;
        });
    }
};

// === Formular-Logik ===
const TrainerForm = {
    comboboxes: {},

    init() {
        this.initComboboxes();
        this.initFotoUpload();
        this.initProjekte();
        this.initAbschluesse();
        this.initGeocode();
        this.initCharCounter();
        this.initValidation();
    },

    initComboboxes() {
        document.querySelectorAll('[data-combobox]').forEach(el => {
            const cb = new Combobox(el, {
                kategorie: el.dataset.combobox,
                name: el.dataset.name,
                required: el.dataset.required === 'true',
                multi: el.dataset.multi === 'true',
                value: el.dataset.value || '',
            });
            this.comboboxes[el.dataset.name] = cb;
        });
    },

    initFotoUpload() {
        const fileInput = document.getElementById('foto-input');
        const preview = document.getElementById('foto-preview');
        const hiddenInput = document.getElementById('foto-base64');
        const removeBtn = document.getElementById('foto-remove');

        if (!fileInput) return;

        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (file.size > 512000) {
                Toast.show('Foto darf maximal 500 KB groß sein.', 'error');
                fileInput.value = '';
                return;
            }

            if (!file.type.startsWith('image/')) {
                Toast.show('Bitte ein Bild auswählen.', 'error');
                fileInput.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (ev) => {
                const base64 = ev.target.result;
                hiddenInput.value = base64;
                preview.innerHTML = `<img src="${base64}" alt="Vorschau">`;
            };
            reader.readAsDataURL(file);
        });

        removeBtn?.addEventListener('click', () => {
            hiddenInput.value = '';
            preview.innerHTML = '📷';
            fileInput.value = '';
        });
    },

    initProjekte() {
        const container = document.getElementById('projekte-list');
        const addBtn = document.getElementById('add-projekt');
        if (!container || !addBtn) return;

        addBtn.addEventListener('click', () => {
            const items = container.querySelectorAll('.dynamic-list-item');
            if (items.length >= 5) {
                Toast.show('Maximal 5 Projekte möglich.', 'warning');
                return;
            }

            const item = document.createElement('div');
            item.className = 'dynamic-list-item';
            item.innerHTML = `
                <div class="item-fields">
                    <input type="text" name="projekte[]" maxlength="200"
                           placeholder="z.B. mindscool Grundschulprogramm Hamburg (2023–heute)">
                </div>
                <button type="button" class="btn-icon btn-remove" title="Entfernen">${Icons.remove}</button>
            `;
            container.appendChild(item);

            item.querySelector('.btn-remove').addEventListener('click', () => {
                item.remove();
                this.updateProjektBtn();
            });

            this.updateProjektBtn();
        });

        // Bestehende Entfernen-Buttons
        container.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.dynamic-list-item').remove();
                this.updateProjektBtn();
            });
        });
    },

    updateProjektBtn() {
        const container = document.getElementById('projekte-list');
        const addBtn = document.getElementById('add-projekt');
        if (!container || !addBtn) return;
        const items = container.querySelectorAll('.dynamic-list-item');
        addBtn.disabled = items.length >= 5;
    },

    initAbschluesse() {
        const container = document.getElementById('abschluesse-list');
        const addBtn = document.getElementById('add-abschluss');
        if (!container || !addBtn) return;

        addBtn.addEventListener('click', () => {
            this.addAbschlussRow(container);
        });

        // Bestehende Entfernen-Buttons
        container.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.dynamic-list-item').remove();
            });
        });
    },

    addAbschlussRow(container, data = {}) {
        const idx = container.querySelectorAll('.dynamic-list-item').length;
        const item = document.createElement('div');
        item.className = 'dynamic-list-item';
        item.innerHTML = `
            <div class="item-fields">
                <div class="item-row">
                    <div data-combobox="abschlussarten" data-name="abschluss_art_${idx}"
                         data-placeholder="Abschlussart wählen..." data-required="true"
                         data-value="${data.art || ''}"></div>
                    <div data-combobox="faecher" data-name="abschluss_fach_${idx}"
                         data-placeholder="Fach/Studiengang wählen..." data-required="true"
                         data-value="${data.fach || ''}"></div>
                </div>
                <input type="text" name="abschluss_zusatz_${idx}"
                       placeholder="z.B. Universität Hamburg, 2015, Schwerpunkt Klinische Psychologie"
                       value="${data.zusatzinfo || ''}">
            </div>
            <button type="button" class="btn-icon btn-remove" title="Entfernen">${Icons.remove}</button>
        `;
        container.appendChild(item);

        // Comboboxen initialisieren
        item.querySelectorAll('[data-combobox]').forEach(el => {
            const cb = new Combobox(el, {
                kategorie: el.dataset.combobox,
                name: el.dataset.name,
                required: el.dataset.required === 'true',
                value: el.dataset.value || '',
            });
            this.comboboxes[el.dataset.name] = cb;
        });

        item.querySelector('.btn-remove').addEventListener('click', () => {
            // Combobox-Referenzen entfernen
            item.querySelectorAll('[data-combobox]').forEach(el => {
                delete this.comboboxes[el.dataset.name];
            });
            item.remove();
        });
    },

    initGeocode() {
        const wohnortInput = document.getElementById('wohnort');
        const plzInput = document.getElementById('plz');
        if (!wohnortInput) return;

        let timeout;
        const triggerGeocode = () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => this.geocode(), 500);
        };
        wohnortInput.addEventListener('blur', triggerGeocode);
        if (plzInput) plzInput.addEventListener('blur', triggerGeocode);
    },

    async geocode() {
        const ort = document.getElementById('wohnort')?.value || '';
        const plz = document.getElementById('plz')?.value || '';
        if (!ort && !plz) return;

        const latInput = document.getElementById('wohnort_lat');
        const lngInput = document.getElementById('wohnort_lng');
        if (!latInput || !lngInput) return;

        const query = [plz, ort, 'Germany'].filter(Boolean).join(' ');

        try {
            const resp = await fetch(
                `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`,
                { headers: { 'Accept-Language': 'de' } }
            );
            const data = await resp.json();

            if (data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);
                latInput.value = lat;
                lngInput.value = lng;
                this.updatePreviewMap(lat, lng);
            }
        } catch (err) {
            console.error('Geocoding-Fehler:', err);
        }
    },

    previewMap: null,

    updatePreviewMap(lat, lng) {
        const mapEl = document.getElementById('map-preview');
        if (!mapEl || typeof L === 'undefined') return;

        if (!this.previewMap) {
            this.previewMap = L.map('map-preview').setView([lat, lng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 18,
            }).addTo(this.previewMap);
            this.previewMarker = L.marker([lat, lng], { icon: MapMarkerIcon }).addTo(this.previewMap);

            // Klick auf Karte zum Korrigieren
            this.previewMap.on('click', (e) => {
                const { lat, lng } = e.latlng;
                this.previewMarker.setLatLng([lat, lng]);
                document.getElementById('wohnort_lat').value = lat.toFixed(6);
                document.getElementById('wohnort_lng').value = lng.toFixed(6);
            });
        } else {
            this.previewMap.setView([lat, lng], 12);
            this.previewMarker.setLatLng([lat, lng]);
        }
    },

    initCharCounter() {
        document.querySelectorAll('[data-maxlength]').forEach(el => {
            const max = parseInt(el.dataset.maxlength);
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            el.parentNode.appendChild(counter);

            const update = () => {
                const len = el.value.length;
                counter.textContent = `${len}/${max}`;
                counter.classList.toggle('limit-near', len > max * 0.8);
                counter.classList.toggle('limit-reached', len >= max);
            };

            el.addEventListener('input', update);
            update();
        });
    },

    initValidation() {
        const form = document.getElementById('trainer-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Comboboxen validieren
            let valid = true;
            for (const [name, cb] of Object.entries(this.comboboxes)) {
                if (!cb.validate()) valid = false;
            }

            // Standard-Inputs validieren
            form.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('invalid');
                    valid = false;
                } else {
                    input.classList.remove('invalid');
                }
            });

            // E-Mail validieren
            const emailInput = form.querySelector('[type="email"]');
            if (emailInput && emailInput.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
                emailInput.classList.add('invalid');
                valid = false;
            }

            if (!valid) {
                Toast.show('Bitte alle Pflichtfelder korrekt ausfüllen.', 'error');
                // Zum ersten fehlerhaften Feld scrollen
                const firstInvalid = form.querySelector('.invalid');
                if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            await this.submit(form);
        });
    },

    async submit(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        const origText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Speichern...';

        try {
            const data = this.collectFormData(form);
            const editId = form.dataset.editId;

            if (editId) {
                data.id = editId;
                await apiPost('update', data);
                Toast.show('Profil erfolgreich aktualisiert!', 'success');
            } else {
                await apiPost('create', data);
                Toast.show('Profil erfolgreich angelegt!', 'success');
            }

            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = origText;
        }
    },

    collectFormData(form) {
        const data = {
            vorname: form.querySelector('[name="vorname"]')?.value || '',
            nachname: form.querySelector('[name="nachname"]')?.value || '',
            email: form.querySelector('[name="email"]')?.value || '',
            telefon: form.querySelector('[name="telefon"]')?.value || '',
            webseite: form.querySelector('[name="webseite"]')?.value || '',
            foto_base64: form.querySelector('[name="foto_base64"]')?.value || '',
            plz: form.querySelector('[name="plz"]')?.value || '',
            wohnort: form.querySelector('[name="wohnort"]')?.value || '',
            land: form.querySelector('[name="land"]')?.value || '',
            wohnort_lat: parseFloat(form.querySelector('[name="wohnort_lat"]')?.value) || 0,
            wohnort_lng: parseFloat(form.querySelector('[name="wohnort_lng"]')?.value) || 0,
            einsatzgebiete: this.comboboxes['einsatzgebiete']?.getValue() || [],
            angebote: [],
            aktuelle_taetigkeit: form.querySelector('[name="aktuelle_taetigkeit"]')?.value || '',
            wirkungsfelder: form.querySelector('[name="wirkungsfelder"]')?.value || '',
            projekte: [],
            akademische_abschluesse: [],
        };

        // Angebote sammeln (Checkboxen)
        form.querySelectorAll('[name="angebote[]"]:checked').forEach(cb => {
            data.angebote.push(cb.value);
        });

        // Projekte sammeln
        form.querySelectorAll('[name="projekte[]"]').forEach(input => {
            if (input.value.trim()) data.projekte.push(input.value.trim());
        });

        // Abschlüsse sammeln
        const abschlussItems = document.getElementById('abschluesse-list')?.querySelectorAll('.dynamic-list-item') || [];
        abschlussItems.forEach((item, idx) => {
            const artCb = this.comboboxes[`abschluss_art_${idx}`];
            const fachCb = this.comboboxes[`abschluss_fach_${idx}`];
            const zusatz = item.querySelector(`[name="abschluss_zusatz_${idx}"]`)?.value || '';

            if (artCb?.getValue() && fachCb?.getValue()) {
                data.akademische_abschluesse.push({
                    art: artCb.getValue(),
                    fach: fachCb.getValue(),
                    zusatzinfo: zusatz,
                });
            }
        });

        return data;
    }
};

// === Taxonomie-Verwaltung ===
const TaxAdmin = {
    activeTab: 'abschlussarten',

    async init() {
        this.bindTabs();
        await this.loadTab(this.activeTab);
    },

    bindTabs() {
        document.querySelectorAll('.tab[data-kategorie]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.activeTab = tab.dataset.kategorie;
                this.loadTab(this.activeTab);
            });
        });
    },

    async loadTab(kategorie) {
        const content = document.getElementById('tax-content');
        if (!content) return;

        try {
            const usage = await apiGet('taxonomie_usage', { kategorie });

            // Vorschläge zählen
            const vorschlaege = usage.filter(e => e.status === 'vorschlag');
            const banner = document.getElementById('vorschlag-banner');
            if (banner) {
                if (vorschlaege.length > 0) {
                    banner.innerHTML = `Es gibt <strong>${vorschlaege.length}</strong> neue Vorschläge zur Überprüfung.`;
                    banner.classList.remove('hidden');
                } else {
                    banner.classList.add('hidden');
                }
            }

            // Tabelle rendern
            this.renderTable(content, kategorie, usage);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    },

    renderTable(container, kategorie, data) {
        // Sortierung: Vorschläge oben, dann alphabetisch
        data.sort((a, b) => {
            if (a.status === 'vorschlag' && b.status !== 'vorschlag') return -1;
            if (a.status !== 'vorschlag' && b.status === 'vorschlag') return 1;
            return a.name.localeCompare(b.name, 'de');
        });

        // Suchfeld
        const searchVal = document.getElementById('tax-search')?.value?.toLowerCase() || '';
        if (searchVal) {
            data = data.filter(e => e.name.toLowerCase().includes(searchVal));
        }

        let html = `<table class="data-table">
            <thead><tr>
                <th>Name</th>
                <th>Status</th>
                <th>Verwendung</th>
                <th>Aktionen</th>
            </tr></thead>
            <tbody>`;

        if (data.length === 0) {
            html += '<tr><td colspan="4" class="no-data">Keine Einträge gefunden.</td></tr>';
        }

        data.forEach(e => {
            const statusBadge = e.status === 'aktiv'
                ? '<span class="badge badge-success">aktiv</span>'
                : '<span class="badge badge-warning">Vorschlag</span>';

            const canDelete = e.count === 0;
            const deleteTitle = canDelete
                ? 'Löschen'
                : `Wird noch von ${e.count} Trainer*in(nen) verwendet – erst zusammenführen`;

            html += `<tr data-id="${e.id}">
                <td data-label="Name">
                    <span class="tax-name">${e.name}</span>
                </td>
                <td data-label="Status">${statusBadge}</td>
                <td data-label="Verwendung">${e.count}</td>
                <td data-label="Aktionen" class="cell-actions">
                    <button class="btn-icon" title="Umbenennen" onclick="TaxAdmin.startRename('${kategorie}', '${e.id}', this)">${Icons.edit}</button>
                    ${e.status === 'vorschlag' ? `<button class="btn-icon" title="Freigeben" onclick="TaxAdmin.approve('${kategorie}', '${e.id}')">${Icons.approve}</button>` : ''}
                    <button class="btn-icon" title="Zusammenführen" onclick="TaxAdmin.showMerge('${kategorie}', '${e.id}', '${e.name}')">${Icons.merge}</button>
                    <button class="btn-icon" title="${deleteTitle}" ${!canDelete ? 'disabled' : ''} onclick="TaxAdmin.delete('${kategorie}', '${e.id}', '${e.name}')">${Icons.trash}</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';

        // Hinzufügen-Formular
        html += `<div class="tax-add-form">
            <input type="text" id="tax-new-name" placeholder="Neuen Eintrag hinzufügen...">
            <button class="btn btn-primary btn-sm" onclick="TaxAdmin.add('${kategorie}')">Hinzufügen</button>
        </div>`;

        container.innerHTML = html;
    },

    startRename(kategorie, id, btn) {
        const td = btn.closest('tr').querySelector('.tax-name');
        const oldName = td.textContent;

        td.innerHTML = `<div class="inline-edit">
            <input type="text" value="${oldName}" id="rename-input-${id}">
            <button class="btn-icon" onclick="TaxAdmin.doRename('${kategorie}', '${id}')">${Icons.check}</button>
            <button class="btn-icon" onclick="TaxAdmin.loadTab('${kategorie}')">${Icons.cross}</button>
        </div>`;

        const input = document.getElementById(`rename-input-${id}`);
        input.focus();
        input.select();
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') TaxAdmin.doRename(kategorie, id);
            if (e.key === 'Escape') TaxAdmin.loadTab(kategorie);
        });
    },

    async doRename(kategorie, id) {
        const input = document.getElementById(`rename-input-${id}`);
        const neuerName = input?.value?.trim();
        if (!neuerName) return;

        try {
            await apiPost('taxonomie_rename', { kategorie, id, neuer_name: neuerName });
            Toast.show('Eintrag umbenannt.', 'success');
            this.loadTab(kategorie);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    },

    async approve(kategorie, id) {
        try {
            await apiPost('taxonomie_approve', { kategorie, id });
            Toast.show('Vorschlag freigegeben.', 'success');
            this.loadTab(kategorie);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    },

    showMerge(kategorie, quellId, quellName) {
        // Modal anzeigen
        const overlay = document.getElementById('merge-modal');
        if (!overlay) return;

        document.getElementById('merge-quell-name').textContent = quellName;
        document.getElementById('merge-quell-id').value = quellId;
        document.getElementById('merge-kategorie').value = kategorie;

        // Ziel-Dropdown befüllen
        const select = document.getElementById('merge-ziel');
        select.innerHTML = '<option value="">Bitte wählen...</option>';

        apiGet('taxonomie_list', { kategorie }).then(eintraege => {
            eintraege.filter(e => e.id !== quellId).forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.name;
                select.appendChild(opt);
            });
        });

        overlay.classList.add('open');
    },

    closeMerge() {
        document.getElementById('merge-modal')?.classList.remove('open');
    },

    async doMerge() {
        const kategorie = document.getElementById('merge-kategorie').value;
        const quellId = document.getElementById('merge-quell-id').value;
        const zielId = document.getElementById('merge-ziel').value;

        if (!zielId) {
            Toast.show('Bitte ein Ziel auswählen.', 'error');
            return;
        }

        try {
            await apiPost('taxonomie_merge', { kategorie, quell_id: quellId, ziel_id: zielId });
            Toast.show('Einträge zusammengeführt.', 'success');
            this.closeMerge();
            this.loadTab(kategorie);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    },

    async delete(kategorie, id, name) {
        if (!confirm(`Eintrag „${name}" wirklich löschen?`)) return;

        try {
            await apiPost('taxonomie_delete', { kategorie, id });
            Toast.show('Eintrag gelöscht.', 'success');
            this.loadTab(kategorie);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    },

    async add(kategorie) {
        const input = document.getElementById('tax-new-name');
        const name = input?.value?.trim();
        if (!name) return;

        try {
            await apiPost('taxonomie_add', { kategorie, name });
            Toast.show(`„${name}" hinzugefügt.`, 'success');
            input.value = '';
            this.loadTab(kategorie);
        } catch (err) {
            Toast.show('Fehler: ' + err.message, 'error');
        }
    }
};

// === PDF-Export (clientseitig mit jsPDF) ===
const PDFExport = {
    _logoDataUrl: null,
    _logoLoaded: false,

    /**
     * Logo einmalig als Data-URL laden (DOM-unabhängig)
     */
    async _loadLogo() {
        if (this._logoLoaded) return this._logoDataUrl;
        try {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            await new Promise((resolve, reject) => {
                img.onload = resolve;
                img.onerror = reject;
                img.src = 'assets/logo.png';
            });
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            canvas.getContext('2d').drawImage(img, 0, 0);
            this._logoDataUrl = canvas.toDataURL('image/png');
        } catch (e) {
            this._logoDataUrl = null;
        }
        this._logoLoaded = true;
        return this._logoDataUrl;
    },

    /**
     * Logo ins PDF einfügen, gibt neue y-Position zurück
     */
    _addLogo(doc, y, margin) {
        if (this._logoDataUrl) {
            doc.addImage(this._logoDataUrl, 'PNG', margin, y, 50, 15);
            return y + 22;
        }
        return y;
    },

    async generateTable(dozenten) {
        if (typeof window.jspdf === 'undefined') {
            Toast.show('PDF-Bibliothek wird geladen...', 'warning');
            return;
        }

        await this._loadLogo();

        const JsPDF = window.jspdf.jsPDF;
        const doc = new JsPDF({ orientation: 'landscape' });
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const margin = 14;
        const colWidths = [50, 20, 35, 40, 55, 70]; // Name, PLZ, Wohnort, Einsatzgebiet, Angebote, Wirkungsfelder
        const headers = ['Name', 'PLZ', 'Wohnort', 'Einsatzgebiet', 'Angebote', 'Wirkungsfelder'];

        // Logo
        let y = 12;
        y = this._addLogo(doc, y, margin);

        // Titel
        doc.setFontSize(16);
        doc.setTextColor(45, 52, 54);
        doc.text('mindscool Trainer*innen-Pool', margin, y + 2);
        doc.setFontSize(9);
        doc.setTextColor(112, 112, 115);
        doc.text(`Stand: ${new Date().toLocaleDateString('de-DE')} – ${dozenten.length} Einträge`, margin, y + 8);

        y += 15;

        const drawHeader = () => {
            doc.setFillColor(28, 167, 219);
            doc.rect(margin, y, pageWidth - 2 * margin, 8, 'F');
            doc.setFontSize(9);
            doc.setTextColor(255, 255, 255);
            doc.setFont(undefined, 'bold');
            let x = margin + 2;
            headers.forEach((h, i) => {
                doc.text(h, x, y + 5.5);
                x += colWidths[i];
            });
            doc.setFont(undefined, 'normal');
            y += 10;
        };

        drawHeader();

        dozenten.forEach((d, idx) => {
            const angebote = (d.angebote || []).join(', ');
            const wirkung = [angebote, d.wirkungsfelder].filter(Boolean).join('; ');

            const cells = [
                `${d.nachname}, ${d.vorname}`,
                d.plz || '–',
                `${d.wohnort || '–'}${d.land ? ' (' + d.land + ')' : ''}`,
                (d.einsatzgebiete || []).join(', ') || '–',
                angebote || '–',
                d.wirkungsfelder || '–',
            ];

            // Zeilenhöhe berechnen (mehrzeilige Zellen)
            doc.setFontSize(8);
            const wrappedCells = cells.map((text, i) => doc.splitTextToSize(text, colWidths[i] - 4));
            const lineCount = Math.max(...wrappedCells.map(c => c.length));
            const rowHeight = Math.max(7, lineCount * 4 + 3);

            // Seitenumbruch?
            if (y + rowHeight > pageHeight - 15) {
                // Fußzeile
                doc.setFontSize(7);
                doc.setTextColor(112, 112, 115);
                doc.text(`mindscool – Trainer*innen-Pool`, pageWidth / 2, pageHeight - 8, { align: 'center' });
                doc.addPage();
                y = 15;
                drawHeader();
            }

            // Zebrastreifen
            if (idx % 2 === 0) {
                doc.setFillColor(245, 247, 250);
                doc.rect(margin, y, pageWidth - 2 * margin, rowHeight, 'F');
            }

            doc.setTextColor(45, 52, 54);
            let x = margin + 2;
            wrappedCells.forEach((lines, i) => {
                doc.text(lines, x, y + 4);
                x += colWidths[i];
            });

            y += rowHeight;
        });

        // Letzte Fußzeile
        doc.setFontSize(7);
        doc.setTextColor(112, 112, 115);
        doc.text(`mindscool – Trainer*innen-Pool`, pageWidth / 2, pageHeight - 8, { align: 'center' });

        doc.save(`mindscool_Trainerpool_Tabelle_${new Date().toISOString().slice(0,10)}.pdf`);
        Toast.show('Tabellen-PDF exportiert.', 'success');
    },

    async generateSingle(dozent) {
        if (typeof window.jspdf === 'undefined') {
            Toast.show('PDF-Bibliothek wird geladen...', 'warning');
            return;
        }

        await this._loadLogo();

        const JsPDF = window.jspdf.jsPDF;
        const doc = new JsPDF();
        const pageWidth = doc.internal.pageSize.getWidth();
        let y = 20;

        // Logo
        y = this._addLogo(doc, y, 14);

        // Trennlinie
        doc.setDrawColor(28, 167, 219); // --color-blue
        doc.setLineWidth(0.5);
        doc.line(14, y, pageWidth - 14, y);
        y += 10;

        // Name
        doc.setFontSize(22);
        doc.setTextColor(45, 52, 54); // --color-text
        doc.text(`${dozent.vorname} ${dozent.nachname}`, 14, y);
        y += 10;

        // Kontakt
        doc.setFontSize(11);
        doc.setTextColor(112, 112, 115); // --color-grey
        doc.text(`${dozent.wohnort} | ${(dozent.einsatzgebiete || []).join(', ')}`, 14, y);
        y += 6;
        doc.text(`${dozent.email}${dozent.telefon ? ' | ' + dozent.telefon : ''}`, 14, y);
        y += 6;
        if (dozent.webseite) {
            doc.text(dozent.webseite, 14, y);
            y += 6;
        }
        y += 6;

        // Angebote
        if (dozent.angebote?.length > 0) {
            doc.setFontSize(12);
            doc.setTextColor(28, 167, 219); // --color-blue
            doc.text('Angebote', 14, y);
            y += 6;
            doc.setFontSize(10);
            doc.setTextColor(45, 52, 54);
            doc.text(dozent.angebote.join(', '), 14, y);
            y += 8;
        }

        // Wirkungsfelder
        if (dozent.wirkungsfelder) {
            doc.setFontSize(12);
            doc.setTextColor(28, 167, 219);
            doc.text('Weitere Wirkungsfelder', 14, y);
            y += 6;
            doc.setFontSize(10);
            doc.setTextColor(45, 52, 54);
            const lines = doc.splitTextToSize(dozent.wirkungsfelder, pageWidth - 28);
            doc.text(lines, 14, y);
            y += lines.length * 5 + 8;
        }

        // Aktuelle Tätigkeit
        if (dozent.aktuelle_taetigkeit) {
            doc.setFontSize(12);
            doc.setTextColor(155, 81, 224); // --color-purple
            doc.text('Aktuelle Tätigkeit', 14, y);
            y += 6;
            doc.setFontSize(10);
            doc.setTextColor(45, 52, 54);
            const lines = doc.splitTextToSize(dozent.aktuelle_taetigkeit, pageWidth - 28);
            doc.text(lines, 14, y);
            y += lines.length * 5 + 8;
        }

        // Akademische Abschlüsse
        if (dozent.akademische_abschluesse?.length > 0) {
            doc.setFontSize(12);
            doc.setTextColor(160, 59, 139);
            doc.text('Akademische Abschlüsse', 14, y);
            y += 7;

            doc.setFontSize(10);
            doc.setTextColor(45, 52, 54);
            dozent.akademische_abschluesse.forEach(a => {
                doc.setFont(undefined, 'bold');
                doc.text(`${a.art} – ${a.fach}`, 14, y);
                y += 5;
                if (a.zusatzinfo) {
                    doc.setFont(undefined, 'normal');
                    doc.setTextColor(112, 112, 115);
                    doc.text(a.zusatzinfo, 14, y);
                    doc.setTextColor(45, 52, 54);
                    y += 5;
                }
                y += 2;
            });
            y += 4;
        }

        // Projekte
        if (dozent.projekte?.length > 0) {
            doc.setFontSize(12);
            doc.setTextColor(160, 59, 139);
            doc.text('Projekte', 14, y);
            y += 7;

            doc.setFontSize(10);
            doc.setTextColor(45, 52, 54);
            dozent.projekte.forEach((p, i) => {
                const lines = doc.splitTextToSize(`${i + 1}. ${p}`, pageWidth - 28);
                doc.text(lines, 14, y);
                y += lines.length * 5 + 2;
            });
        }

        // Fußzeile
        const now = new Date().toLocaleDateString('de-DE');
        doc.setFontSize(8);
        doc.setTextColor(112, 112, 115);
        doc.text(
            `mindscool – Trainer*innen-Pool – Stand: ${now}`,
            pageWidth / 2,
            doc.internal.pageSize.getHeight() - 10,
            { align: 'center' }
        );

        doc.save(`${dozent.nachname}_${dozent.vorname}_Profil.pdf`);
    },

    async generateAll(dozenten) {
        if (typeof window.jspdf === 'undefined') {
            Toast.show('PDF-Bibliothek wird geladen...', 'warning');
            return;
        }

        await this._loadLogo();

        const JsPDF = window.jspdf.jsPDF;
        const doc = new JsPDF();
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();

        // Deckblatt
        let y = 60;
        if (this._logoDataUrl) {
            doc.addImage(this._logoDataUrl, 'PNG', pageWidth / 2 - 40, 30, 80, 24);
            y = 70;
        }

        doc.setFontSize(28);
        doc.setTextColor(45, 52, 54);
        doc.text('Trainer*innen-Pool', pageWidth / 2, y, { align: 'center' });
        y += 10;
        doc.setFontSize(14);
        doc.setTextColor(112, 112, 115);
        doc.text('Übersicht', pageWidth / 2, y, { align: 'center' });
        y += 8;
        doc.text(new Date().toLocaleDateString('de-DE'), pageWidth / 2, y, { align: 'center' });

        // Inhaltsverzeichnis
        doc.addPage();
        y = 20;
        doc.setFontSize(16);
        doc.setTextColor(45, 52, 54);
        doc.text('Inhaltsverzeichnis', 14, y);
        y += 10;

        doc.setFontSize(11);
        dozenten.forEach((d, i) => {
            doc.text(`${i + 1}. ${d.vorname} ${d.nachname}`, 14, y);
            y += 6;
            if (y > pageHeight - 20) {
                doc.addPage();
                y = 20;
            }
        });

        // Pro Person eine Seite
        dozenten.forEach(d => {
            doc.addPage();
            y = 20;

            doc.setFontSize(18);
            doc.setTextColor(45, 52, 54);
            doc.text(`${d.vorname} ${d.nachname}`, 14, y);
            y += 8;

            doc.setFontSize(10);
            doc.setTextColor(112, 112, 115);
            doc.text(`${d.wohnort} | ${(d.einsatzgebiete || []).join(', ')}`, 14, y);
            y += 5;
            doc.text(`${d.email}${d.telefon ? ' | ' + d.telefon : ''}`, 14, y);
            y += 5;
            if (d.webseite) {
                doc.text(d.webseite, 14, y);
                y += 5;
            }
            y += 3;

            if (d.angebote?.length > 0) {
                doc.setFontSize(10);
                doc.setTextColor(28, 167, 219);
                doc.text('Angebote: ', 14, y);
                doc.setTextColor(45, 52, 54);
                doc.text(d.angebote.join(', '), 14 + doc.getTextWidth('Angebote: '), y);
                y += 5;
            }

            if (d.wirkungsfelder) {
                doc.setFontSize(10);
                doc.setTextColor(45, 52, 54);
                const lines = doc.splitTextToSize(d.wirkungsfelder, pageWidth - 28);
                doc.text(lines, 14, y);
                y += lines.length * 5 + 3;
            }

            if (d.aktuelle_taetigkeit) {
                doc.setFontSize(10);
                doc.setTextColor(45, 52, 54);
                const lines = doc.splitTextToSize(d.aktuelle_taetigkeit, pageWidth - 28);
                doc.text(lines, 14, y);
                y += lines.length * 5 + 4;
            }

            if (d.akademische_abschluesse?.length > 0) {
                doc.setFontSize(11);
                doc.setTextColor(160, 59, 139);
                doc.text('Abschlüsse:', 14, y);
                y += 6;
                doc.setFontSize(10);
                doc.setTextColor(45, 52, 54);
                d.akademische_abschluesse.forEach(a => {
                    doc.text(`• ${a.art} ${a.fach}${a.zusatzinfo ? ' – ' + a.zusatzinfo : ''}`, 14, y);
                    y += 5;
                });
                y += 3;
            }

            if (d.projekte?.length > 0) {
                doc.setFontSize(11);
                doc.setTextColor(160, 59, 139);
                doc.text('Projekte:', 14, y);
                y += 6;
                doc.setFontSize(10);
                doc.setTextColor(45, 52, 54);
                d.projekte.forEach(p => {
                    const lines = doc.splitTextToSize(`• ${p}`, pageWidth - 28);
                    doc.text(lines, 14, y);
                    y += lines.length * 5;
                });
            }

            // Fußzeile
            doc.setFontSize(8);
            doc.setTextColor(112, 112, 115);
            doc.text(
                `mindscool – Trainer*innen-Pool – Stand: ${new Date().toLocaleDateString('de-DE')}`,
                pageWidth / 2, pageHeight - 10, { align: 'center' }
            );
        });

        doc.save('mindscool_Trainerpool_Uebersicht.pdf');
    }
};

// === Initialisierung ===
document.addEventListener('DOMContentLoaded', () => {
    Toast.init();

    // Seiten-spezifische Initialisierung
    if (document.getElementById('dashboard-page')) {
        Dashboard.init();
    }
    if (document.getElementById('trainer-form')) {
        TrainerForm.init();
    }
    if (document.getElementById('taxonomie-page')) {
        TaxAdmin.init();
    }

    // Taxonomie-Suche
    document.getElementById('tax-search')?.addEventListener('input', () => {
        TaxAdmin.loadTab(TaxAdmin.activeTab);
    });
});
