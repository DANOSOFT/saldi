// Settings module
class SettingsManager {
    constructor() {
        this.initializeElements();
        this.initializeSettings();
    }

    async initializeElements() {
        this.elements = {
            save: document.querySelector('.save'),
            format: document.querySelector('.format'),
            searchCustNr: document.querySelector('#kundenr'),
            searchCustName: document.querySelector('#navn'),
            searchCustTlf: document.querySelector('#tlf'),
            startDay: document.querySelector('#indflytning'),
            deletion: document.querySelector('.sletning'),
            findWeeks: document.querySelector('.findUger'),
            endDay: document.querySelector('#udflytning'),
            putTogether: document.querySelector('.putTogether'),
            invoiceDate: document.querySelector('#fakturadato'),
            usePassword: document.querySelector('#use_password'),
            password: document.querySelector('#password'),
            toggleOrder: document.querySelector("#toggleOrder")
        };

        this.elements.save.addEventListener('click', (e) => this.handleSave(e));
    }

    async initializeSettings() {
        const url = new URL(window.location.href);
        const pathSegments = url.pathname.split('/').filter(segment => segment !== '');
        const firstFolder = pathSegments[0];
        const { getSettings, updateSettings } = await import(`/${firstFolder}/rental/api/api.js`);
        
        this.api = { getSettings, updateSettings };
        this.settings = await this.api.getSettings();
        
        await this.checkPassword();
        this.populateSettings();
    }

    async checkPassword() {
        if (this.settings.use_password === "1") {
            const pass = prompt("Indtast adgangskode for at fortsætte");
            if (pass !== this.settings.pass) {
                alert("Forkert adgangskode");
                const currentUrl = new URL(window.location.href);
                const currentPathSegments = currentUrl.pathname.split('/').filter(segment => segment !== '');
                const redirectFolder = currentPathSegments[0];
                window.location.href = `/${redirectFolder}/rental/index.php?vare`;
            }
        }
    }

    populateSettings() {
        const { elements, settings } = this;
        
        elements.format.value = settings.booking_format;
        elements.searchCustNr.checked = settings.search_cust_number === '1';
        elements.searchCustName.checked = settings.search_cust_name === '1';
        elements.searchCustTlf.checked = settings.search_cust_tlf === '1';
        elements.startDay.checked = settings.start_day === '1';
        elements.deletion.checked = settings.deletion === '1';
        elements.findWeeks.checked = settings.find_weeks === '1';
        elements.endDay.checked = settings.end_day === '1';
        elements.putTogether.checked = settings.put_together === '1';
        elements.invoiceDate.checked = settings.invoice_date === '1';
        elements.usePassword.checked = settings.use_password === '1';
        elements.password.value = settings.pass || "";
        elements.toggleOrder.checked = settings.toggle_order === '1';
    }

    async handleSave(e) {
        e.preventDefault();
        
        if (this.elements.usePassword.checked && !this.elements.password.value) {
            alert("Du skal udfylde adgangskoden for at gemme ændringerne.");
            return;
        }

        const data = {
            booking_format: parseInt(this.elements.format.value),
            search_cust_number: this.elements.searchCustNr.checked ? 1 : 0,
            search_cust_name: this.elements.searchCustName.checked ? 1 : 0,
            search_cust_tlf: this.elements.searchCustTlf.checked ? 1 : 0,
            start_day: this.elements.startDay.checked ? 1 : 0,
            deletion: this.elements.deletion.checked ? 1 : 0,
            find_weeks: this.elements.findWeeks.checked ? 1 : 0,
            end_day: this.elements.endDay.checked ? 1 : 0,
            put_together: this.elements.putTogether.checked ? 1 : 0,
            invoice_date: this.elements.invoiceDate.checked ? 1 : 0,
            use_password: this.elements.usePassword.checked ? 1 : 0,
            password: this.elements.password.value,
            toggle_order: this.elements.toggleOrder.checked ? 1 : 0
        };

        const result = await this.api.updateSettings(data);
        alert(result);
    }
}

// Initialize the settings manager
(async () => {
    new SettingsManager();
})();
