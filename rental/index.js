import {getCustomers, deleteBooking} from "/pos/rental/api/api.js"

const editIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
<path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
<path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
</svg>`

const deleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
</svg>`

let currentDate = new Date()
let currentMonth = currentDate.getMonth()
let currentYear = currentDate.getFullYear()
const months = ["Januar", "Febuar", "Marts", "April", "Maj", "Juni", "Juli", "August", "September", "Oktober", "November", "December"]

const getDaysInMonth = (year, month) => {
    const date = new Date(year, month, 1)
    const days = []
    while(date.getMonth() === month){
        days.push(new Date(date).getDate())
        date.setDate(date.getDate() + 1)
    }
    return days
}

const getCustomerDates = async () => {
    const data = await getCustomers()

    if(data === "Der er ingen bookinger"){
        return []
    }

    return data.map(i => {
        const fromDate = new Date(i.from * 1000)
        const fromDateFormattad = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
        const toDate = new Date(i.to * 1000)
        const toDateFormattad = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
        return [fromDateFormattad, toDateFormattad, i.account_number, i.booking_id]
    })
}

// Button eventListener
document.querySelector(".backward").addEventListener("click", () => {
    if (currentMonth > 0) {
        currentMonth--
    } else {
        currentMonth = 11
        currentYear--
    }
    createCalendar()
})

// Button eventListener
document.querySelector(".forward").addEventListener("click", () => {
    if (currentMonth < 11) {
        currentMonth++
    } else {
        currentMonth = 0
        currentYear++
    }
    createCalendar()
})

const createCalendar = async () => {
    const monthString = months[currentMonth]
    const table = document.querySelector(".table-point")
    const span = document.querySelector(".month")
    const createAppoint = document.querySelector(".createAppointment")

    span.innerHTML = `<a href="index.php?month=${currentMonth}&year=${currentYear}">${monthString} ${currentYear}</a>`

    const days = getDaysInMonth(currentYear, currentMonth)

    const custDates = await getCustomerDates()
    let appointment = false

    let rows = []
    let currentRow = []
    for(let i = 0; i < days.length; i++){
        const txtNode = document.createTextNode(days[i])
        const td = document.createElement("td")
        const currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + days[i]).slice(-2)
        custDates.forEach(date => {
            if(currentDate >= date[0] && currentDate <= date[1]){
                const a = document.createElement("a")
                a.setAttribute("class", "link-primary")
                a.href = `index.php?year=${currentYear}&month=${currentMonth}&day=${days[i]}`
                a.appendChild(txtNode)
                td.appendChild(a)
                appointment = true
            }
        })

        if(!appointment) {
            td.appendChild(txtNode)
        }
        appointment = false

        currentRow.push(td.outerHTML)
        if (i % 7 === 6) {
            rows.push(currentRow)
            currentRow = []
        }
    }

    // Add any remaining cells to the last row
    if (currentRow.length > 0) {
        while (currentRow.length < 7) {
            currentRow.push("<td></td>")
        }
        rows.push(currentRow)
    }

    // Create the table
    let newTable = "<table class='table text-white table-borderless'>"
    newTable += "<tbody class='tBody'>"
    for (let i = 0; i < rows.length; i++) {
        newTable += "<tr>"
        for (let j = 0; j < rows[i].length; j++) {
            newTable += rows[i][j]
        }
        newTable += "</tr>"
    }
    newTable += "</tbody></table>"
    table.innerHTML = newTable
}

// Define reusable functions
const formatTime = (date) => {
    return ("0" + date.getHours()).slice(-2) + ":" + ("0" + date.getMinutes()).slice(-2)
}

const formatTimeAndDate = (date) => {
    const month = date.getMonth() + 1
    return `${date.getDate()}/${month}/${date.getFullYear()} ${formatTime(date)}`
}

const formatDate = (date) => {
    const month = date.getMonth() + 1
    return `${date.getDate()}/${month}/${date.getFullYear()}`
}
  
  // Define the main function
const createReservationList = async (year, month, day) => {
    // Parse input values to integers
    month = parseInt(month) + 1
    year = parseInt(year)
    day = parseInt(day)

    // Hide some elements
    document.querySelector(".backward").hidden = true
    document.querySelector(".forward").hidden = true

    // Setting some styles
    document.querySelector(".flex-content").setAttribute("class", "text-center")
    document.querySelector(".table-point").innerHTML = "<table class='table table-dark table-striped'><tbody class='tBody'></tbody></table>"
    document.querySelector(".calendar").style.width = "80%"

    // Set the month label
    const span = document.querySelector(".month")
    span.appendChild(document.createTextNode(`${day}/${month}/${year}`))

    // Set the table headers
    const tBody = document.querySelector(".tBody")
    const tr = document.createElement("tr")
    tr.innerHTML = `
        <th>Navn</th>
        <th>Konto Nr</th>
        <th>Fra</th>
        <th>Til</th>
        <th>Redigere</th>
        <th>Slet</th>
    `
    tBody.appendChild(tr)

    // Get customer data and process it
    const customers = await getCustomers();
    const customerInfo = customers.map((customer) => ({
        name: customer.name,
        account_number: customer.account_number,
        fromDate: new Date(customer.from * 1000),
        toDate: new Date(customer.to * 1000),
        id: customer.id,
        booking_id: customer.booking_id
    }))
    const customerDates = await getCustomerDates()
    const selectedDate = year + "-" + ("0" + month).slice(-2) + "-" + ("0" + day).slice(-2)

    // Populate the table
    customerDates.forEach((date) => {
        if (selectedDate >= date[0] && selectedDate <= date[1]){
            const customer = customerInfo.find((c) => c.account_number === date[2])
            if (customer) {
            const tr = document.createElement("tr")
            tr.innerHTML = `
                <td>${customer.name}</td>
                <td>${customer.account_number}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                ? formatTime(customer.fromDate)
                : formatDate(customer.fromDate)}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                ? formatTime(customer.toDate)
                : formatDate(customer.toDate)}</td>
                <td>
                <a href="edit.php?id=${customer.id}">
                    <button class="btn btn-primary">${editIcon}</button>
                </a>
                </td>
                <td>
                    <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                </td>
            `
            tBody.appendChild(tr)
            }
        }
    })

    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            const id = e.target.id
            const response = await deleteBooking(id)
            alert(response)
            window.location.reload()
        })
    })
        
}

const monthlyOverview = async (year, month) => {
   // Parse input values to integers
   month = parseInt(month) + 1
   year = parseInt(year)

   // Hide some elements
   document.querySelector(".backward").hidden = true
   document.querySelector(".forward").hidden = true

   // Setting some styles
   document.querySelector(".flex-content").setAttribute("class", "text-center")
   document.querySelector(".table-point").innerHTML = "<table class='table table-dark table-striped'><tbody class='tBody'></tbody></table>"
   document.querySelector(".calendar").style.width = "80%"

   // Set the month label
   const span = document.querySelector(".month")
   span.appendChild(document.createTextNode(("0" + month).slice(-2) + "/" + year))

   // Set the table headers
   const tBody = document.querySelector(".tBody")
   const tr = document.createElement("tr")
    tr.innerHTML = `
        <th>Navn</th>
        <th>Konto Nr</th>
        <th>Fra</th>
        <th>Til</th>
        <th>Redigere</th>
        <th>Slet</th>
    `
   tBody.appendChild(tr)

   // Get customer data and process it
    const customers = await getCustomers();
    const customerInfo = customers.map((customer) => ({
        name: customer.name,
        account_number: customer.account_number,
        fromDate: new Date(customer.from * 1000),
        toDate: new Date(customer.to * 1000),
        id: customer.id,
        booking_id: customer.booking_id
    }))
    const customerDates = await getCustomerDates()

   // Populate the table
    customerDates.forEach((date) => {
        month = ("0" + month).slice(-2)
       if (month == date[0].split("-")[1] && year == date[0].split("-")[0] || (month == date[1].split("-")[1] && year == date[1].split("-")[0])){
            const customer = customerInfo.find((c) => c.account_number === date[2])
            if (customer) {
            const tr = document.createElement("tr")
            tr.innerHTML = `
                <td>${customer.name}</td>
                <td>${customer.account_number}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                ? formatTimeAndDate(customer.fromDate)
                : formatDate(customer.fromDate)}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                ? formatTimeAndDate(customer.toDate)
                : formatDate(customer.toDate)}</td>
                <td>
                <a href="edit.php?id=${customer.id}">
                    <button class="btn btn-primary">${editIcon}</button>
                </a>
                </td>
                <td>
                    <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                </td>
            `
            tBody.appendChild(tr)
            }
       }
   })

   const deleteButtons = document.querySelectorAll(".delete")
   deleteButtons.forEach(button => {
       button.addEventListener("click", async (e) => {
           const id = e.target.id
           const response = await deleteBooking(id)
           alert(response)
           window.location.reload()
       })
   })
}

// Init the calendar or the reservation list
const queryString = window.location.search
if(queryString !== ""){
    const urlParams = new URLSearchParams(queryString)
if(urlParams.has("day")&& urlParams.get("month") && urlParams.get("year")){
    const day = urlParams.get("day")
    const month = urlParams.get("month")
    const year = urlParams.get("year")
    createReservationList(year, month, day)
    }else if(urlParams.get("month") && urlParams.get("year")){
        const month = urlParams.get("month")
        const year = urlParams.get("year")
        monthlyOverview(year, month)
    }
}else{
    createCalendar()
}