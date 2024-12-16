// Make sure you're in an async context
(async () => {
    const url = new URL(window.location.href)
    const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
    const firstFolder = pathSegments[0]
    // Dynamically import the module
    const { getAllCustomers, getSettings, getBookingsByCust } = await import(`/${firstFolder}/rental/api/api.js`)

const createOptionElementDatalist = (value, text) => {
    const option = document.createElement("option")
    option.dataset.value = value
    option.text = text
    return option
  }


const settings = await getSettings()
const customers = await getAllCustomers()

const bookingsDiv = document.querySelector(".bookings")
const cust = document.querySelector(".customers")
const searchInput = document.querySelector(".customers-search")
cust.innerHTML = ""

customers.forEach(c => {
let name = ""
if(settings.search_cust_name != "1" && settings.search_cust_tlf != "1" && settings.search_cust_number != "1"){
    name = c.name
}
if(settings.search_cust_name === "1"){
    name = c.name + " "
}
if(settings.search_cust_tlf === "1"){
    name += c.phone + " "
}
if(settings.search_cust_number === "1"){
    name += c.account_number
}
const option = createOptionElementDatalist(c.id, name)
cust.appendChild(option)
})

const optionsList = document.getElementById("customers").options
let id
searchInput.addEventListener("change", async () => {
    const selectedValue = searchInput.value
    bookingsDiv.innerHTML = ""
    for (let i = 0; i < optionsList.length; i++) {
        const option = optionsList[i]
        if (option.text === selectedValue) {
            id = option.dataset.value
            break // Exit loop once we find the matching option
        }
    }
    const bookings = await getBookingsByCust(id)
    if(bookings != null){
        // order bookings by to date DESC
        bookings.sort((a, b) => b.to - a.to)
        const table = document.createElement("table")
        const tbody = document.createElement("tbody")
        table.classList.add("table", "table-responsive-sm", "table-light", "table-striped")
        tbody.classList.add("tBody")
        const tr = document.createElement("tr")
        const th1 = document.createElement("th")
        const th2 = document.createElement("th")
        const th3 = document.createElement("th")
        const th4 = document.createElement("th")
        th1.innerHTML = "Stand navn"
        th2.innerHTML = "Fra"
        th3.innerHTML = "Til"
        th4.innerHTML = "Periode"
        tr.appendChild(th1)
        tr.appendChild(th2)
        tr.appendChild(th3)
        tr.appendChild(th4)
        tbody.appendChild(tr)
        table.appendChild(tbody)
        bookings.forEach(b => {
            const tr = document.createElement("tr")
            const td1 = document.createElement("td")
            const td2 = document.createElement("td")
            const td3 = document.createElement("td")
            const td4 = document.createElement("td")
            const fromDate = new Date(b.from * 1000)
            const toDate = new Date(b.to * 1000)
            const from = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
            const to = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
            const itemName = b.item_name
            // get period length in weeks
            const period = Math.round((b.to - b.from) / 604800)
            td1.innerHTML = itemName
            td2.innerHTML = from
            td3.innerHTML = to
            td4.innerHTML = period + " uger"
            tr.appendChild(td1)
            tr.appendChild(td2)
            tr.appendChild(td3)
            tr.appendChild(td4)
            tbody.appendChild(tr)
        })
        bookingsDiv.appendChild(table)
    }else{
        const div = document.createElement("div")
        div.innerHTML = "Ingen bookinger"
        bookingsDiv.appendChild(div)
    }
})
})()