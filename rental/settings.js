// Make sure you're in an async context
(async () => {
    const url = new URL(window.location.href)
    const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
    const firstFolder = pathSegments[0]
    // Dynamically import the module
    const { getSettings, updateSettings } = await import(`/${firstFolder}/rental/api/api.js`)

const save = document.querySelector('.save')
const format = document.querySelector('.format')
const searchCustNr = document.querySelector('#kundenr')
const searchCustName = document.querySelector('#navn')
const searchCustTlf = document.querySelector('#tlf')
const startDay = document.querySelector('#indflytning')
const deletion = document.querySelector('.sletning')
const findWeeks = document.querySelector('.findUger')
const endDay = document.querySelector('#udflytning')
const putTogether = document.querySelector('.putTogether')
const invoiceDate = document.querySelector('#fakturadato')
const use_password = document.querySelector('#use_password')
const password = document.querySelector('#password')
const toggleOrder = document.querySelector("#toggleOrder")
const settings = await getSettings()

if(settings.use_password == "1"){
    const pass = prompt("Indtast adgangskode for at fortsætte")
    if(pass != settings.pass){
        console.log(pass + " " + settings.pass)
        alert("Forkert adgangskode")
        // get the first folder in url
        const currentUrl = new URL(window.location.href)
        const currentPathSegments = currentUrl.pathname.split('/').filter(segment => segment !== '')
        const redirectFolder = currentPathSegments[0]
        window.location.href = `/${redirectFolder}/rental/index.php?vare`
    }
}

format.value = settings.booking_format
searchCustNr.checked = (settings.search_cust_number == 1) ? true : false
searchCustName.checked = (settings.search_cust_name == 1) ? true : false
searchCustTlf.checked = (settings.search_cust_tlf == 1) ? true : false
startDay.checked = (settings.start_day == 1) ? true : false
deletion.checked = (settings.deletion == 1) ? true : false
findWeeks.checked = (settings.find_weeks == 1) ? true : false
endDay.checked = (settings.end_day == 1) ? true : false
putTogether.checked = (settings.put_together == 1) ? true : false
invoiceDate.checked = (settings.invoice_date == 1) ? true : false
use_password.checked = (settings.use_password == 1) ? true : false
password.value = (settings.pass == null || settings.pass == undefined) ? "" : settings.pass
toggleOrder.checked = (settings.toggle_order == 1) ? true : false

save.addEventListener('click', async e => {
    e.preventDefault()
    if(use_password.checked == true && password.value == ''){
        alert("Du skal udfylde adgangskoden for at gemme ændringerne.")
    }
    const data = {
        booking_format: parseInt(format.value),
        search_cust_number: searchCustNr.checked ? 1 : 0,
        search_cust_name: searchCustName.checked ? 1 : 0,
        search_cust_tlf: searchCustTlf.checked ? 1 : 0,
        start_day: startDay.checked ? 1 : 0,
        deletion: deletion.checked ? 1 : 0,
        find_weeks: findWeeks.checked ? 1 : 0,
        end_day: endDay.checked ? 1 : 0,
        put_together: putTogether.checked ? 1 : 0,
        invoice_date: invoiceDate.checked ? 1 : 0,
        use_password: use_password.checked ? 1 : 0,
        password: password.value,
        toggle_order: toggleOrder.checked ? 1: 0
    }
    const res = await updateSettings(data)
    alert(res)
})
})()
