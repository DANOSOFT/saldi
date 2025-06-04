// Make sure you're in an async context
(async () => {
    const url = new URL(window.location.href)
    const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
    const firstFolder = pathSegments[0]
    // Dynamically import the module
    const { getCustomers, deleteBooking, createReservation, getProductInfos, getBooking, getReservations, getClosedDays, editReservationComment, getBookingsForItemsByType, getReservationsByItem, deleteReservation, getItem, getBookingByCustomer, getSettings, getItemBookings, updateSettings, editReservationDates, getBookingsForItems, getBookingsFromPeriod } = await import(`/${firstFolder}/rental/api/api.js`)

    //
//
//  Hvis du skal ændre noget i koden så held og lykke
//  Der er ikke lavet kommentarer til alt
//  Hvis du har brug for hjælp så kontakt mig
//  - Patrick Madsen
//


let scrollPosition = 0
let availableItems = [], searchInput
let itemsInfo = {}
const searchItems = []
const queryString = window.location.search
const settings = await getSettings()
if(settings.success == false){
    const data = {
        booking_format: 2,
        search_cust_number: 1,
        search_cust_name: 1,
        search_cust_tlf: 1,
        start_day: 0,
        deletion: 0,
        find_weeks: 0,
        end_day: 0,
        put_together: 0,
        use_password: 0,
        password: "",
        invoice_date: 0,
        toggle_order: 1
    }
    const res = await updateSettings(data)
}
const editIcon = `<svg xmlns="http://www.w3.org/2000/svg" style="pointer-events: none;" width="24" height="24" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
<path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
<path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
</svg>`

const deleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" style="pointer-events: none;" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
</svg>`

const sortUp = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sort-up" viewBox="0 0 16 16">
  <path d="M3.5 12.5a.5.5 0 0 1-1 0V3.707L1.354 4.854a.5.5 0 1 1-.708-.708l2-1.999.007-.007a.498.498 0 0 1 .7.006l2 2a.5.5 0 1 1-.707.708L3.5 3.707V12.5zm3.5-9a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zM7.5 6a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 3a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zm0 3a.5.5 0 0 0 0 1h1a.5.5 0 0 0 0-1h-1z"/>
</svg>`
const sortDown = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-sort-down" viewBox="0 0 16 16">
  <path d="M3.5 2.5a.5.5 0 0 0-1 0v8.793l-1.146-1.147a.5.5 0 0 0-.708.708l2 1.999.007.007a.497.497 0 0 0 .7-.006l2-2a.5.5 0 0 0-.707-.708L3.5 11.293V2.5zm3.5 1a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zM7.5 6a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zm0 3a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1h-3zm0 3a.5.5 0 0 0 0 1h1a.5.5 0 0 0 0-1h-1z"/>
</svg>`

let currentDate = new Date()
let currentMonth = currentDate.getMonth()
let currentYear = currentDate.getFullYear()
const months = ["Januar", "Febuar", "Marts", "April", "Maj", "Juni", "Juli", "August", "September", "Oktober", "November", "December"]
const daysChar = ["Søn", "Man", "Tir", "Ons", "Tor", "Fre", "Lør"]
const daysSingleChar = ["Sø", "Ma", "Ti", "On", "To", "Fr", "Lø"]
const getDaysInMonth = (year, month) => {
    const date = new Date(year, month, 1)
    const days = []
    while (date.getMonth() === month) {
        days.push(new Date(date).getDate())
        date.setDate(date.getDate() + 1)
    }
    return days
}

window.availableOnDay = async (date, productInfo, value) => {
    const select = document.querySelector(".transparent")
    let itemIds = []
    const reservations = await getReservations()
    if(value == "one"){
        itemIds = new Set(productInfo.map(item => item.item_id))
    }else if(value == "two"){
        itemIds = new Set(productInfo.map(item => item.item_id))
    }else{
        itemIds = new Set(productInfo.map(item => item.product_id == value ? item.item_id : null))
    }
    // Filter the item IDs
    const availableItemIds = Array.from(itemIds).filter(itemId => {
        // Find all bookings for this item
        const bookings = productInfo.filter(item => item.item_id === itemId)

        // Check if any of the bookings overlap with the given period
        const hasBookingDuringPeriod = bookings.some(booking => {
            if (!date || booking.fromDate === 'NaN-aN-aN' || booking.toDate === 'NaN-aN-aN') {
                // If the booking doesn't have valid dates, it doesn't overlap with the given period
                return false
            }

            const bookingFromDate = new Date(booking.fromDate)
            const bookingToDate = new Date(booking.toDate)
            const givenFromDate = new Date(date)
            const givenToDate = new Date(date)
            // Check if the booking overlaps with the given period
            return bookingFromDate <= givenFromDate && bookingToDate >= givenFromDate ||
                bookingFromDate <= givenToDate && bookingToDate >= givenToDate ||
                bookingFromDate >= givenFromDate && bookingToDate <= givenToDate
        })
        // check if there is any reservations in the given period
        let hasReservationDuringPeriod
        if(reservations.success == false){
            resBool = false
        }else{
            hasReservationDuringPeriod = reservations.some(reservation => {
                if(reservation.item_id == itemId){
                    const from = new Date(reservation.from * 1000)
                    const to = new Date(reservation.to * 1000)
                    // check if the reservation is in the current month or later months
                    if(to > new Date()){
                        // check if day is in reservation period
                        return from <= new Date(date) && to >= new Date(date)
                    }
                }
            })
        }

        // The item is available if it doesn't have any bookings during the given period
        return !hasBookingDuringPeriod && !hasReservationDuringPeriod
    })

    // Get the available items
    availableItems = productInfo.filter(item => availableItemIds.includes(item.item_id))
    const event = new Event('change')
    select.value = "available"
    select.dispatchEvent(event)
}

const getCustomerDates = async (month, year) => {
    const data = await getCustomers(month, year)

    if (data === "Der er ingen bookinger") {
        return []
    }
    return data.map(i => {
        const fromDate = new Date(i.from * 1000)
        let toDatePlus
        const fromDateFormatted = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
        const toDate = new Date(i.to * 1000)
        const toDateFormatted = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
        if(settings.end_day == 1){
            // set it to the day before
            toDatePlus = new Date(toDate)
            toDatePlus.setDate(toDatePlus.getDate() - 1)
            toDatePlus = toDatePlus.getFullYear() + "-" + ("0" + (toDatePlus.getMonth() + 1)).slice(-2) + "-" + ("0" + toDatePlus.getDate()).slice(-2)
        }
        return [fromDateFormatted, toDateFormatted, i.account_number, i.booking_id, i.item_name, i.product_id, toDatePlus, i.item_id]
    })
}

//backward button function
const backward = async () => {
    const backwardButton = document.querySelector(".backward")
    backwardButton.disabled = true
    setTimeout(() => {
        backwardButton.disabled = false
    }, 1500);
    scrollPosition = window.scrollY || document.documentElement.scrollTop
    const urlParams = new URLSearchParams(queryString)
    if (urlParams.has("vare")) {
        /* if (urlParams.has("month") && urlParams.has("year")) { */
            if (currentMonth > 0) {
                currentMonth--
            } else {
                currentMonth = 11
                currentYear--
            }
       /*  } */
    } else {
        if (currentMonth > 0) {
            currentMonth--
        } else {
            currentMonth = 11
            currentYear--
        }
    }
    if (urlParams.has("vare")) {
        let value = document.querySelector(".transparent").value
        /* if (urlParams.has("month") && urlParams.has("year")) { */
        await productOverviewMonth(currentMonth, currentYear, value)
        /* } else {
            productOverview(value)
        } */
        window.scrollTo(0, scrollPosition)
    } else {
        createCalendar()
    }
}

// Button eventListener
document.querySelector(".backward").addEventListener("click", backward)

// forward button function
const forward = async () => {
    const forwardButton = document.querySelector(".forward")
    forwardButton.disabled = true
    setTimeout(() => {
        forwardButton.disabled = false
    }, 1500);
    scrollPosition = window.scrollY || document.documentElement.scrollTop
    const urlParams = new URLSearchParams(queryString)
    if (urlParams.has("vare")) {
        /* if (urlParams.has("month") && urlParams.has("year")) { */
            if (currentMonth < 11) {
                currentMonth++
            } else {
                currentMonth = 0
                currentYear++
            }
       /*  } else {
            currentYear++
        } */
    } else {
        if (currentMonth < 11) {
            currentMonth++
        } else {
            currentMonth = 0
            currentYear++
        }
    }
    if (urlParams.has("vare")) {
        let value = document.querySelector(".transparent").value
        /* if (urlParams.has("month") && urlParams.has("year")) { */
        await productOverviewMonth(currentMonth, currentYear, value)
/*         } else {
            productOverview(value)
        } */
        window.scrollTo(0, scrollPosition)
    } else {
        createCalendar()
    }
}

// Button eventListener
document.querySelector(".forward").addEventListener("click", forward)

// Set arrow key functionality
document.addEventListener('keydown', function(event) {
    if (event.key === "ArrowLeft") {
        // Trigger the backward function when the left arrow key is pressed
        backward()
    } else if (event.key === "ArrowRight") {
        // Trigger the forward function when the right arrow key is pressed
        forward()
    }
})

// function for going forward 1 day
const goForward = (day, month, year, value) => {
    if (day < getDaysInMonth(year, month-1).length) {
        day++
    }
    else if (month < 12) {
        month++
        day = 1
    }
    else {
        year++
        month = 1
        day = 1
    }
    createReservationList(year, month - 1, day, value)
}

// function for going back 1 day
const goBack = (day, month, year, value) => {
    if (day > 1) {
        day--
    }
    else if (month > 1) {
        month--
        day = getDaysInMonth(year, month-1).length
    }
    else {
        year--
        month = 12
        day = getDaysInMonth(year, month-1).length
    }
    createReservationList(year, month - 1, day, value)
}

let handleDay, handleMonth, handleYear, handleValue

// Define your event handler functions
const handleBackClick = () => {
    goBack(handleDay, handleMonth, handleYear, handleValue)
}

const handleForwardClick = () => {
    goForward(handleDay, handleMonth, handleYear, handleValue)
}



const createCalendar = async () => {
    const monthString = months[currentMonth]
    const table = document.querySelector(".table-point")
    const span = document.querySelector(".month")

    span.innerHTML = `<p>${monthString} ${currentYear}</p>`

    const days = getDaysInMonth(currentYear, currentMonth)
    let custDates = await getCustomerDates(currentMonth+1, currentYear)

    // check if a customer have booked the same item twice in a row dependent on date
    if(settings.put_together == "1"){
        // Sort the customerInfo array by customer id
        let mergedCustomerDates = []

        custDates.sort((a, b) => a[2] - b[2] || a[4].localeCompare(b[4]) || new Date(a[0]) - new Date(b[0]))
        for (let i = 0; i < custDates.length; i++) {
            if (i > 0 && 
                custDates[i][2] === custDates[i-1][2] && 
                custDates[i][4] === custDates[i-1][4] && 
                Math.abs((new Date(custDates[i][0]) - new Date(custDates[i-1][1])) / (1000 * 60 * 60 * 24)) < 2) {
                
                mergedCustomerDates[mergedCustomerDates.length - 1][1] = custDates[i][1]
            } else {
                mergedCustomerDates.push(custDates  [i])
            }
        }
        custDates = mergedCustomerDates
    }
    if(custDates.length == 0){
        table.innerHTML = "<p class='text-center'>Der er ingen bookinger</p>"
        return
    }

    let rows = []
    let currentRow = []

    for (let i = 0; i < days.length; i++) {
        const txtNode = document.createTextNode(days[i])
        const td = document.createElement("td")
        let currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + days[i]).slice(-2)
        let today = new Date()
        today = today.getFullYear() + "-" + ("0" + (today.getMonth() + 1)).slice(-2) + "-" + ("0" + today.getDate()).slice(-2)
        const a = document.createElement("a")
        let j = 0
        custDates.forEach(date => {
            let startDate = new Date(date[0])
            if(settings.start_day == 1) {
                startDate.setDate(startDate.getDate() - 1)
            }
            startDate = startDate.getFullYear() + "-" + ("0" + (startDate.getMonth() + 1)).slice(-2) + "-" + ("0" + startDate.getDate()).slice(-2)
            const endDate = settings.end_day == 1 ? date[6] : date[1]

            if(currentDate === today){
                a.setAttribute("class", "today-circle link-dark")
                j++
            }else if((currentDate == endDate || currentDate == startDate) && j == 0){
                a.setAttribute("class", "circle link-dark")
                j++
            }
        })
        if (j == 0) {
            a.setAttribute("class", "link-dark")
        }
        a.href = `index.php?year=${currentYear}&month=${currentMonth}&day=${days[i]}&value=one`
        a.appendChild(txtNode)
        td.appendChild(a)

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

    let firstDate = new Date(currentYear+"-"+(currentMonth+1)+"-"+days[0])
    let dayNumber = firstDate.getDay()

    let newTable = "<table class='table table-borderless'>"
    newTable += "<thead><tr>"
    for (let i = 0; i < 7; i++) {
        newTable += `<th>${daysChar[(dayNumber + i) % 7]}</th>`
    }
    newTable += "</tr></thead>"
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
    document.dispatchEvent(new CustomEvent('renderComplete'))
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

const createReservationList = async (year, month, day, value) => {
    // Parse input values to integers
    month = parseInt(month) + 1
    year = parseInt(year)
    day = parseInt(day)
    handleValue = value
    handleDay = day
    handleMonth = month
    handleYear = year

    // Hide some elements
    const back = document.querySelector(".backward")
    const next = document.querySelector(".forward")

    back.removeEventListener("click", backward)
    next.removeEventListener("click", forward)

    // Remove the event listeners before adding them, to ensure they're only added once
    back.removeEventListener('click', handleBackClick)
    back.addEventListener('click', handleBackClick)

    next.removeEventListener('click', handleForwardClick)
    next.addEventListener('click', handleForwardClick)

    // Setting some styles
    const flexContent = document.querySelector(".flex-content")
    if(!flexContent.hasAttribute("class")) flexContent.setAttribute("class", "text-center")
    const tablePoint = document.querySelector(".table-point")
    tablePoint.innerHTML = ""
    if(!tablePoint.hasAttribute("class")) tablePoint.setAttribute("class", "text-center")
    tablePoint.innerHTML = `<table class='table table-responsive-sm table-light table-striped'><tbody class='tBody'></tbody></table>`
    document.querySelector(".calendar").style.width = "80%"

    // get reservations
    const reservations = await getReservations()

    // Set the month label
    const span = document.querySelector(".month")
    span.innerHTML = ""
    span.appendChild(document.createTextNode(`${day}/${month}/${year}`))

    // Set the table headers
    const tBody = document.querySelector(".tBody")
    const tr = document.createElement("tr")
    tr.innerHTML = `
    <th class="sort" id="name">Navn</th>
    <th class="sort" id="tlf">tlf</th>
    <th class="sort" id="product">Udlejnings produkt</th>
    <th class="sort" id="kundenr">Kundenr</th>
    <th class="sort" id="from">Fra</th>
    <th class="sort" id="to">Til</th>
        <th>Redigere</th>
        <th>Slet</th>
    `
    // Get customer data and process it
    const customers = await getCustomers(month, year)
    let customerInfo = customers.map((customer) => {
        let fromDate = new Date(customer.from * 1000)
        let toDate = new Date(customer.to * 1000)

        return {
            name: customer.name,
            account_number: customer.account_number,
            fromDate: fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2),
            toDate: toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2),
            id: customer.id,
            booking_id: customer.booking_id,
            item_name: customer.item_name,
            order_status: customer.order_status,
            tlf: customer.tlf,
            item_id: customer.item_id
        }
    })
    
    // check if a customer have booked the same item twice in a row dependent on date
    if(settings.put_together == "1"){
        // Sort the customerInfo array by id and item_name
        let mergedArray = []

        customerInfo.sort((a, b) => a.id - b.id || a.item_name.localeCompare(b.item_name) || new Date(a.fromDate) - new Date(b.fromDate))

        for (let i = 0; i < customerInfo.length; i++) {
            if (i > 0 && 
                customerInfo[i].id === customerInfo[i-1].id && 
                customerInfo[i].item_name === customerInfo[i-1].item_name && 
                Math.abs((new Date(customerInfo[i].fromDate) - new Date(customerInfo[i-1].toDate)) / (1000 * 60 * 60 * 24)) < 2) {
                mergedArray[mergedArray.length - 1].toDate = customerInfo[i].toDate
            } else {
                mergedArray.push(customerInfo[i])
            }
        }
        
        customerInfo = mergedArray
    }

    let selectedDate = year + "-" + ("0" + month).slice(-2) + "-" + ("0" + day).slice(-2)
    let startSelectedDate = year + "-" + ("0" + month).slice(-2) + "-" + ("0" + day).slice(-2)
    if(settings.start_day == 1){
        const date = new Date(selectedDate)
        date.setDate(date.getDate() + 1)
        startSelectedDate = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
    }

    let check = false
    // check if there is any bookings starting startSelectedDate
    customerInfo.some(date => {
        if(startSelectedDate == date.fromDate){
            const header = document.createElement("h3")
            header.innerHTML = "Kommende bookinger"
            header.setAttribute("class", "text-center mt-4 mb-4 ")
            // append as first child
            tablePoint.insertBefore(header, tablePoint.firstChild)
            tBody.appendChild(tr)
            return true
        }
    })
    const dates = customerInfo.filter(date => date.toDate == selectedDate)


    let tbodyTwo
    if(dates != "" && dates.length != 0 && settings.end_day != 1){
        const tr = document.createElement("tr")
        tr.innerHTML = `
            <th class="sort" id="name">Navn</th>
            <th class="sort" id="tlf">tlf</th>
            <th class="sort" id="product">Udlejnings produkt</th>
            <th class="sort" id="kundenr">Kundenr</th>
            <th class="sort" id="from">Fra</th>
            <th class="sort" id="to">Til</th>
            <th>Redigere</th>
            <th>Slet</th>
        `
        const header = document.createElement("h3")
        header.innerHTML = "Udgår i dag"
        header.setAttribute("class", "text-center")
        tablePoint.appendChild(header)
        const table = document.createElement("table")
        table.setAttribute("class", "table table-responsive-sm table-light table-striped")
        tbodyTwo = document.createElement("tbody")
        tbodyTwo.setAttribute("class", "tBody")
        table.appendChild(tbodyTwo)
        tbodyTwo.appendChild(tr)
        tablePoint.appendChild(table)
    }
    
    // Sort customerInfo nummeric based on item_name
    const stripParentheses = (str) => {
        if (!str) return '' // Return empty string if str is null/undefined
        return str.replace(/\(.*?\)/g, '').trim()
    }

    // Sort customerInfo with numeric items first and items with letters last
    customerInfo.sort((a, b) => {
        
        // Helper function to extract number before hyphen
        const getNumberBeforeHyphen = (str) => {
            if (!str) return ''
            const match = str.match(/^(\d+)/)
            return match ? parseInt(match[0], 10) : str
        }
    
        const strippedA = stripParentheses(a.item_name)
        const strippedB = stripParentheses(b.item_name)
    
        // Get numbers before hyphen
        const numA = getNumberBeforeHyphen(strippedA)
        const numB = getNumberBeforeHyphen(strippedB)
    
        // If both items start with numbers, compare the numbers
        if (typeof numA === 'number' && typeof numB === 'number') {
            return numA - numB
        }
    
        // If one item starts with a number and the other doesn't, number comes first
        if (typeof numA === 'number') return -1
        if (typeof numB === 'number') return 1
    
        // If neither starts with a number, compare as strings
        return strippedA.localeCompare(strippedB)
    })

    for (const date of customerInfo) {
        if (value == "one") {
            if(settings.start_day == 1){
                if(startSelectedDate == date.fromDate){
                    const customer = date
                    if (customer) {
                        const reserved = await getReservationsByItem(date.item_id)
                        const dateOne = new Date(date.toDate)
                        let oneDayAfter = new Date(dateOne.getDate() + 1)
                        oneDayAfter = oneDayAfter.getFullYear() + "-" + ("0" + (oneDayAfter.getMonth() + 1)).slice(-2) + "-" + ("0" + oneDayAfter.getDate()).slice(-2)
                        if(!reserved.success){
                            reserved.from = new Date(reserved.from * 1000)
                            reserved.to = new Date(reserved.to * 1000)
                            reserved.from = reserved.from.getFullYear() + "-" + ("0" + (reserved.from.getMonth() + 1)).slice(-2) + "-" + ("0" + reserved.from.getDate()).slice(-2)
                            reserved.to = reserved.to.getFullYear() + "-" + ("0" + (reserved.to.getMonth() + 1)).slice(-2) + "-" + ("0" + reserved.to.getDate()).slice(-2)
                            // if booking is inbetween reserved dates or it starts the day after booking ends
                            if((date.fromDate >= reserved.from && date.fromDate <= reserved.to) ||
                                (date.toDate >= reserved.from && date.toDate <= reserved.to) ||
                                (oneDayAfter === reserved.from)){
                                    customer.item_name += " (Spærret)"
                            }
                        }
                        if(!tBody.firstChild){
                            const tr = document.createElement("tr")
                            tr.innerHTML = `
                            <th class="sort" id="name">Navn</th>
                            <th class="sort" id="tlf">tlf</th>
                            <th class="sort" id="product">Udlejnings produkt</th>
                            <th class="sort" id="kundenr">Kundenr</th>
                            <th class="sort" id="from">Fra</th>
                            <th class="sort" id="to">Til</th>
                            <th>Redigere</th>
                            <th>Slet</th>
                            `
                            tBody.appendChild(tr)   
                        }
                        const tr = document.createElement("tr")
                        tr.innerHTML = `
                        <td>${customer.name}</td>
                        <td>${customer.tlf}</td>
                        <td>${customer.item_name}</td>
                        <td>${customer.account_number}</td>
                        <td>${customer.fromDate}</td>
                        <td>${customer.toDate}</td>
                        <td>
                        <a href="edit.php?id=${customer.booking_id}">
                            <button class="btn btn-primary">${editIcon}</button>
                        </a>
                        </td>
                        <td>
                            <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                        </td>`
                        tBody.appendChild(tr)
                        check = true
                    }
                }else if(selectedDate == date.toDate && settings.end_day != 1){
                    const customer = date
                    if (customer) {
                        const reserved = await getReservationsByItem(date.item_id)
                        const dateOne = new Date(date.toDate)
                        let oneDayAfter = new Date(dateOne)
                        oneDayAfter.setDate(dateOne.getDate() + 1)
                        oneDayAfter = oneDayAfter.getFullYear() + "-" + ("0" + (oneDayAfter.getMonth() + 1)).slice(-2) + "-" + ("0" + oneDayAfter.getDate()).slice(-2)
                        if(reserved.success != false){
                            reserved.some(res => {
                                res.from = new Date(res.from * 1000)
                                res.to = new Date(res.to * 1000)
                                res.from = res.from.getFullYear() + "-" + ("0" + (res.from.getMonth() + 1)).slice(-2) + "-" + ("0" + res.from.getDate()).slice(-2)
                                res.to = res.to.getFullYear() + "-" + ("0" + (res.to.getMonth() + 1)).slice(-2) + "-" + ("0" + res.to.getDate()).slice(-2)
                            
                                // Convert date[0], date[1], and oneDayAfter to date strings in the same format as res.from and res.to
                                let date0 = new Date(date.fromDate)
                                date0 = date0.getFullYear() + "-" + ("0" + (date0.getMonth() + 1)).slice(-2) + "-" + ("0" + date0.getDate()).slice(-2)
                                let date1 = new Date(date.toDate)
                                date1 = date1.getFullYear() + "-" + ("0" + (date1.getMonth() + 1)).slice(-2) + "-" + ("0" + date1.getDate()).slice(-2)

                                // if booking is inbetween reserved dates or it starts the day after booking ends
                                if((date0 >= res.from && date0 <= res.to) ||
                                    (date1 >= res.from && date1 <= res.to) ||
                                    (oneDayAfter == res.from)){
                                        customer.item_name += " (Spærret)"
                                        return true
                                }
                            })
                        }
                        const tr = document.createElement("tr")
                        tr.innerHTML = `
                        <td>${customer.name}</td>
                        <td>${customer.tlf}</td>
                        <td>${customer.item_name}</td>
                        <td>${customer.account_number}</td>
                        <td>${customer.fromDate}</td>
                        <td>${customer.toDate}</td>
                        <td>
                        <a href="edit.php?id=${customer.booking_id}">
                            <button class="btn btn-primary">${editIcon}</button>
                        </a>
                        </td>
                        <td>
                            <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                        </td>`
                        tbodyTwo.appendChild(tr)
                        check = true
                    }
                }
            }else{
                if(startSelectedDate == date.fromDate){
                    const customer = date
                    if (customer) {
                        const tr = document.createElement("tr")
                        tr.innerHTML = `
                        <td>${customer.name}</td>
                        <td>${customer.tlf}</td>
                        <td>${customer.item_name}</td>
                        <td>${customer.account_number}</td>
                        <td>${customer.fromDate}</td>
                        <td>${customer.toDate}</td>
                        <td>
                        <a href="edit.php?id=${customer.booking_id}">
                            <button class="btn btn-primary">${editIcon}</button>
                        </a>
                        </td>
                        <td>
                            <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                        </td>`
                        tBody.appendChild(tr)
                        check = true
                    }
                }else if(selectedDate == date.toDate && settings.end_day != 1){
                    const customer = date
                    if (customer) {
                        const reserved = await getReservationsByItem(date.item_id)
                        const dateOne = new Date(date.toDate)
                        let oneDayAfter = new Date(dateOne)
                        oneDayAfter.setDate(dateOne.getDate() + 1)
                        oneDayAfter = oneDayAfter.getFullYear() + "-" + ("0" + (oneDayAfter.getMonth() + 1)).slice(-2) + "-" + ("0" + oneDayAfter.getDate()).slice(-2)
                        if(reserved.success != false){
                            reserved.some(res => {
                                res.from = new Date(res.from * 1000)
                                res.to = new Date(res.to * 1000)
                                res.from = res.from.getFullYear() + "-" + ("0" + (res.from.getMonth() + 1)).slice(-2) + "-" + ("0" + res.from.getDate()).slice(-2)
                                res.to = res.to.getFullYear() + "-" + ("0" + (res.to.getMonth() + 1)).slice(-2) + "-" + ("0" + res.to.getDate()).slice(-2)
                            
                                // Convert date[0], date[1], and oneDayAfter to date strings in the same format as res.from and res.to
                                let date0 = new Date(date.fromDate)
                                date0 = date0.getFullYear() + "-" + ("0" + (date0.getMonth() + 1)).slice(-2) + "-" + ("0" + date0.getDate()).slice(-2)
                                let date1 = new Date(date.toDate)
                                date1 = date1.getFullYear() + "-" + ("0" + (date1.getMonth() + 1)).slice(-2) + "-" + ("0" + date1.getDate()).slice(-2)

                                // if booking is inbetween reserved dates or it starts the day after booking ends
                                if((date0 >= res.from && date0 <= res.to) ||
                                    (date1 >= res.from && date1 <= res.to) ||
                                    (oneDayAfter == res.from)){
                                        customer.item_name += " (Spærret)"
                                        return true
                                }
                            })
                        }
                        const tr = document.createElement("tr")
                        tr.innerHTML = `
                        <td>${customer.name}</td>
                        <td>${customer.tlf}</td>
                        <td>${customer.item_name}</td>
                        <td>${customer.account_number}</td>
                        <td>${customer.fromDate}</td>
                        <td>${customer.toDate}</td>
                        <td>
                        <a href="edit.php?id=${customer.booking_id}">
                            <button class="btn btn-primary">${editIcon}</button>
                        </a>
                        </td>
                        <td>
                            <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                        </td>`
                        tbodyTwo.appendChild(tr)
                        check = true
                    }
                }
            }
        }
        if((selectedDate == date.fromDate || selectedDate == date.toDate) && value == "two"){
            const customer = date
            if (customer) {
                const tr = document.createElement("tr")
                tr.innerHTML = `
                <td>${customer.name}</td>
                <td>${customer.tlf}</td>
                <td>${customer.item_name}</td>
                <td>${customer.account_number}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                        ? formatTime(customer.fromDate)
                        : formatDate(customer.fromDate)}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                        ? formatTime(customer.toDate)
                        : formatDate(customer.toDate)}</td>
                <td>
                <a href="edit.php?id=${customer.booking_id}">
                    <button class="btn btn-primary">${editIcon}</button>
                </a>
                </td>
                <td>
                    <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                </td>`
                tBody.appendChild(tr)
            }
        }
    }
    if(settings.end_day == 1 && value == "one"){
        // add one day to selectedDate
        const date = new Date(selectedDate)
        date.setDate(date.getDate() + 1)
        selectedDate = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
        const dates = customerInfo.filter(date => date.toDate == selectedDate)
        if(dates != ""){
            if(check == false){
                tBody.innerHTML = ""
            }
            const tr = document.createElement("tr")
            tr.innerHTML = `
                <th class="sort" id="name">Navn</th>
                <th class="sort" id="tlf">tlf</th>
                <th class="sort" id="product">Udlejnings produkt</th>
                <th class="sort" id="kundenr">Kundenr</th>
                <th class="sort" id="from">Fra</th>
                <th class="sort" id="to">Til</th>
                <th>Redigere</th>
                <th>Slet</th>
            `
            
            const header = document.createElement("h3")
            header.innerHTML = "Udgår i morgen"
            header.setAttribute("class", "text-center")
            tablePoint.appendChild(header)
            const table = document.createElement("table")
            table.setAttribute("class", "table table-responsive-sm table-light table-striped")
            const tbody = document.createElement("tbody")
            tbody.setAttribute("class", "tBody")
            table.appendChild(tbody)
            tbody.appendChild(tr)
            dates.forEach(async date => {
                if(selectedDate == date.toDate){
                    const customer = date
                    if(customer){
                        const reserved = await getReservationsByItem(date.item_id)
                        const dateOne = new Date(date.toDate)
                        let oneDayAfter = new Date(dateOne)
                        oneDayAfter.setDate(dateOne.getDate() + 1)
                        oneDayAfter = oneDayAfter.getFullYear() + "-" + ("0" + (oneDayAfter.getMonth() + 1)).slice(-2) + "-" + ("0" + oneDayAfter.getDate()).slice(-2)
                        if(reserved.success != false){
                            reserved.some(res => {
                                res.from = new Date(res.from * 1000)
                                res.to = new Date(res.to * 1000)
                                res.from = res.from.getFullYear() + "-" + ("0" + (res.from.getMonth() + 1)).slice(-2) + "-" + ("0" + res.from.getDate()).slice(-2)
                                res.to = res.to.getFullYear() + "-" + ("0" + (res.to.getMonth() + 1)).slice(-2) + "-" + ("0" + res.to.getDate()).slice(-2)
                            
                                // Convert date[0], date[1], and oneDayAfter to date strings in the same format as res.from and res.to
                                let date0 = new Date(date.fromDate)
                                date0 = date0.getFullYear() + "-" + ("0" + (date0.getMonth() + 1)).slice(-2) + "-" + ("0" + date0.getDate()).slice(-2)
                                let date1 = new Date(date.toDate)
                                date1 = date1.getFullYear() + "-" + ("0" + (date1.getMonth() + 1)).slice(-2) + "-" + ("0" + date1.getDate()).slice(-2)

                                // if booking is inbetween reserved dates or it starts the day after booking ends
                                if((date0 >= res.from && date0 <= res.to) ||
                                    (date1 >= res.from && date1 <= res.to) ||
                                    (oneDayAfter == res.from)){
                                        customer.item_name += " (Spærret)"
                                        return true
                                }
                            })
                        }
                        const tr = document.createElement("tr")
                        tr.innerHTML = `
                        <td>${customer.name}</td>
                        <td>${customer.tlf}</td>
                        <td>${customer.item_name}</td>
                        <td>${customer.account_number}</td>
                        <td>${customer.fromDate}</td>
                        <td>${customer.toDate}</td>
                        <td>
                        <a href="edit.php?id=${customer.booking_id}">
                            <button class="btn btn-primary">${editIcon}</button>
                        </a>
                        </td>
                        <td>
                            <button class="btn btn-danger delete" id="${customer.booking_id}">${deleteIcon}</button>
                        </td>`
                        tbody.appendChild(tr)
                    }
                }
                tablePoint.appendChild(table)
            })
        }
    }
        
    // Add eventListener to delete buttons
    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            const customerInfo = await getBooking(e.target.id)
            if(customerInfo.order_status == 1){
                if(confirm("Er du sikker på at du vil slette en faktureret booking?") === false) return
            }
            if(confirm("Er du sikker på at du vil slette denne booking?") === false) return
            const id = e.target.id
            const response = await deleteBooking(id)
            alert(response)
            window.location.reload()
        })
    })
    const printWeeks = document.createElement("button")
    const printButton = document.createElement("button")
    printButton.className = "btn btn-primary"
    printButton.textContent = "Print"
    printWeeks.className = "btn btn-primary ml-1"
    printWeeks.textContent = "Print x uger"
    printButton.addEventListener("click", () => {
        const printWindow = window.open('', '_blank')
        const tables = document.querySelectorAll('.table')
        // get the weekday of the selected date in like monday, tuesday etc.
        const date = new Date(year, month - 1, day)
        const days = ['Søndag', 'Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag']
        const weekday = days[date.getDay()]
        printWindow.document.write(`<html><head><title>Print</title></head><body><h1>${weekday + " " + day+"/"+month+"/"+year}</h1>`)
        tables.forEach((table, index) => {
            let header = table.previousElementSibling
            while (header && header.tagName.toLowerCase() !== 'h3') {
                header = header.previousElementSibling
            }
            if (header) {
                printWindow.document.write(header.outerHTML)
            }
            let newTable = document.createElement('table')
            newTable.style.borderCollapse = 'collapse'
            for (let i = 0; i < table.rows.length; i++) {
                let newRow = document.createElement('tr')
                for (let j = 0; j < table.rows[i].cells.length - 2; j++) {
                    let newCell = document.createElement('td')
                    newCell.textContent = table.rows[i].cells[j].textContent
                    newCell.style.border = '1px solid black'
                    newCell.style.padding = '10px'
                    newRow.appendChild(newCell)
                }
                newTable.appendChild(newRow)
            }
            printWindow.document.write(newTable.outerHTML)
        })
        printWindow.document.write('</body></html>')
        printWindow.document.close()
        printWindow.print()
    })

    printWeeks.addEventListener("click", async () => {
        const amountOfWeeks = prompt("Vælg antal uger")
        Number(amountOfWeeks)
        if(!isNaN(amountOfWeeks)){
            const xWeeksFromNow = new Date()
            const currentDate = new Date(selectedDate)
            xWeeksFromNow.setDate(currentDate.getDate() + amountOfWeeks * 7)
            const from = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}`
            const to = `${xWeeksFromNow.getFullYear()}-${String(xWeeksFromNow.getMonth() + 1).padStart(2, '0')}-${String(xWeeksFromNow.getDate()).padStart(2, '0')}`
            const bookings = await getBookingsFromPeriod(from, to)
            if(bookings.success && bookings.success == false){
                alert("Der er ikke nogen bookinger i den valgte periode")
                return
            }
            const table = document.createElement("table")
            table.className = "hidden hiddenTable"
            const tBody = document.createElement("tBody")
            let tr = document.createElement("tr")
            tr.innerHTML = "<th>Navn</th><th>tlf</th><th>Udlejnings produkt</th><th>Kundenr</th><th>Fra</th><th>Til</th><th></th><th></th>"
            tBody.appendChild(tr)
            bookings.forEach((booking) => {
                tr = document.createElement("tr")
                const from = new Date(booking.from * 1000)
                const fromFormatted = `${from.getFullYear()}-${String(from.getMonth() + 1).padStart(2, '0')}-${String(from.getDate()).padStart(2, '0')}`
                const to = new Date(booking.to * 1000)
                const toFormatted = `${to.getFullYear()}-${String(to.getMonth() + 1).padStart(2, '0')}-${String(to.getDate()).padStart(2, '0')}`
                tr.innerHTML = `<td>${booking.cust_name}</td><td>${booking.tlf}</td><td>${booking.item_name}</td><td>${booking.kontonr}</td><td>${fromFormatted}</td><td>${toFormatted}</td><td></td><td></td>`
                tBody.appendChild(tr)
            })
            table.appendChild(tBody)
            const body = document.querySelector("body")
            body.appendChild(table)

            const printWindow = window.open('', '_blank')
            const tables = document.querySelectorAll('.hiddenTable')
            // get the weekday of the selected date in like monday, tuesday etc.
            const date = new Date(year, month - 1, day)
            const days = ['Søndag', 'Mandag', 'Tirsdag', 'Onsdag', 'Torsdag', 'Fredag', 'Lørdag']
            const weekday = days[date.getDay()]
            printWindow.document.write(`<html><head><title>Print</title></head><body><h1>${weekday + " " + day+"/"+month+"/"+year}</h1>`)
            tables.forEach((table, index) => {
                let header = table.previousElementSibling
                while (header && header.tagName.toLowerCase() !== 'h3') {
                    header = header.previousElementSibling
                }
                if (header) {
                    printWindow.document.write(header.outerHTML)
                }
                let newTable = document.createElement('table')
                newTable.style.borderCollapse = 'collapse'
                for (let i = 0; i < table.rows.length; i++) {
                    let newRow = document.createElement('tr')
                    for (let j = 0; j < table.rows[i].cells.length - 2; j++) {
                        let newCell = document.createElement('td')
                        newCell.textContent = table.rows[i].cells[j].textContent
                        newCell.style.border = '1px solid black'
                        newCell.style.padding = '10px'
                        newRow.appendChild(newCell)
                    }
                    newTable.appendChild(newRow)
                }
                printWindow.document.write(newTable.outerHTML)
            })
            printWindow.document.write('</body></html>')
            printWindow.document.close()
            printWindow.print()
        }else{
            alert("Du skal skrive et nummer")
        }
    })

    const calendar = document.querySelector(".calendar")
    if(calendar.lastChild && calendar.lastChild.tagName && calendar.lastChild.tagName.toLowerCase() === 'button') {
        calendar.removeChild(calendar.lastChild)
    }
    calendar.appendChild(printButton)
    calendar.appendChild(printWeeks)

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
    document.querySelector(".table-point").innerHTML = "<table class='table table-responsive-sm table-light table-striped'><tbody class='tBody'></tbody></table>"
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
        <th>Udlejnings Product</th>
        <th>Fra</th>
        <th>Til</th>
        <th>Redigere</th>
        <th>Slet</th>
    `
    tBody.appendChild(tr)

    // Get customer data and process it
    const customers = await getCustomers(month, year)
    const customerInfo = customers.map((customer) => ({
        name: customer.name,
        account_number: customer.account_number,
        fromDate: new Date(customer.from * 1000),
        toDate: new Date(customer.to * 1000),
        id: customer.id,
        booking_id: customer.booking_id,
        item_name: customer.item_name
    }))
    /* const customerDates = await getCustomerDates(month, year) */

    // Populate the table
    customers.forEach((date) => {
        month = ("0" + month).slice(-2)
        if (month == date[0].split("-")[1] && year == date[0].split("-")[0] || (month == date[1].split("-")[1] && year == date[1].split("-")[0])) {
            const customer = customerInfo.find((c) => c.account_number === date[2])
            if (customer) {
                const tr = document.createElement("tr")
                tr.innerHTML = `
                <td>${customer.name}</td>
                <td>${customer.account_number}</td>
                <td>${customer.item_name}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                        ? formatTimeAndDate(customer.fromDate)
                        : formatDate(customer.fromDate)}</td>
                <td>${customer.fromDate.getDate() === customer.toDate.getDate()
                        ? formatTimeAndDate(customer.toDate)
                        : formatDate(customer.toDate)}</td>
                <td>
                <a href="edit.php?id=${customer.booking_id}">
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
            if(confirm("Er du sikker på at du vil slette denne booking?") === false) return
            const id = e.target.id
            const response = await deleteBooking(id)
            alert(response)
            window.location.href = "index.php?vare"
        })
    })
}

const productOverviewMonth = async (thisMonth, year, value) => {
    // Setting some styles
    document.querySelector(".table-point").innerHTML = "<table class='table table-bordered table-responsive table-light'></table>"
    if (document.querySelector(".calendar")) document.querySelector(".calendar").setAttribute("class", "remove-margin sticky-container")
    const header = document.querySelector(".header")
    if(header){
        header.setAttribute("class", "sticky-top")
    }
    // Setting Dates
    currentMonth = parseInt(thisMonth)
    currentYear = year
    const product = await getProductInfos(currentMonth+1, currentYear)
    if(product.success == false){
        const tablePoint = document.querySelector(".table-point")
        tablePoint.innerHTML = `<div class="mx-auto" style="width: 40%;"><p>
        Der er ikke sat nogen vare til udlejning.<br>

        For at sætte varer til udlejning skal du følge disse trin:</p>
        <ol>
        <li>Klik på linket herunder</li>
        <li><a href="../lager/varer.php?returside=../index/menu.php">Link til vare</a></li>
        <li>Find den specifikke vare, du ønsker at sætte til udlejning, og klik på den.</li>
        <li>Nederst til højre på skærmen vil du se en knap mærket "Udlejning". Klik på denne knap.</li>
        </ol>
        <p>Når du har fulgt disse trin, vil varen blive markeret som værende til udlejning.</p></div>`
        return
    }
    const table = document.querySelector(".table")
    
    
    // Setting the month label
    const span = document.querySelector(".month")
    span.innerHTML = ""
    span.appendChild(document.createTextNode(months[currentMonth] + " - " + currentYear))

    // Constructing data for the table
    let productInfo = product.map((product) => ({
        product_name: product.product_name,
        item_name: product.item_name,
        fromDate: new Date(product.from * 1000).getFullYear() + "-" + ("0" + (new Date(product.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(product.from * 1000).getDate()).slice(-2),
        toDate: new Date(product.to * 1000).getFullYear() + "-" + ("0" + (new Date(product.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(product.to * 1000).getDate()).slice(-2),
        product_id: product.product_id,
        cust_id: product.cust_id,
        rental_id: product.rental_id,
        cust_name: product.cust_name,
        item_id: product.item_id,
        kontonr: product.kontonr
    }))
    const reservations = await getReservations()

    // sort productInfo by item_name
    productInfo.sort((a, b) => a.item_name.localeCompare(b.item_name))
    
    productInfo.sort((a, b) => parseInt(a.item_name) - parseInt(b.item_name))

    // Creating a list of unique products
    //const uniqueProducts = [...new Set(productInfo.map(item => item.item_id))]

    const days = getDaysInMonth(currentYear, currentMonth)

    // Constructing the table headers
    let tableHeader = `<thead class="sticky-top" style="top: 3.9rem;"><tr><th class='th-lg select' scope='col'></th>`
    let tableContent = ""
    //const month = months[currentMonth]

    let screenWidth = screen.width

    const currentDate = new Date(new Date().getFullYear() + "-" + ("0" + (new Date().getMonth() + 1)).slice(-2) + "-" + ("0" + new Date().getDate()).slice(-2))
    days.forEach(day => {
        let firstDate = new Date(currentYear+"-"+(currentMonth+1)+"-"+day)
        let dayNumber = firstDate.getDay()
        const firstDateNoTime = new Date(firstDate.getFullYear() + "-" + ("0" + (firstDate.getMonth() + 1)).slice(-2) + "-" + ("0" + firstDate.getDate()).slice(-2))
        if(currentDate.getTime() === firstDateNoTime.getTime()){
            tableHeader += `<th scope='col' role="button" class="link-success th-link bg-dark-subtle" title="se ledige stande d.${day} ${months[currentMonth]}" onclick='availableOnDay("${currentYear}-${(currentMonth+1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}", ${JSON.stringify(productInfo)}, "${value}")'>${(screenWidth < 1920) ? daysSingleChar[dayNumber] : daysChar[dayNumber]}<br>${day}</th>`
        }else{
            tableHeader += `<th scope='col' role="button" class="link-primary th-link" title="se ledige stande d.${day} ${months[currentMonth]}" onclick='availableOnDay("${currentYear}-${(currentMonth+1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}", ${JSON.stringify(productInfo)}, "${value}")'>${(screenWidth < 1920) ? daysSingleChar[dayNumber] : daysChar[dayNumber]}<br>${day}</th>`
        }
    })

    tableHeader += "</tr></thead><tbody class='tBody'><div class='eraseTable'>"
    table.innerHTML = tableHeader
    const tBody = document.querySelector(".tBody")
    const eraseTable = document.querySelector(".eraseTable")
    let closedDates
    const getClosedDates = await getClosedDays()
    if(getClosedDates.success == false){
        closedDates = []
    }else{
        closedDates = getClosedDates.map(date => new Date(date.date * 1000).getFullYear() + "-" + ("0" + (new Date(date.date * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(date.date * 1000).getDate()).slice(-2))
    }
    // Constructing data for the table
    const initialTable = () => {
        // Creating a list of unique products
        const uniqueProducts = [...new Set(productInfo.map(item => item.item_id))]
        eraseTable.innerHTML = ""
        uniqueProducts.forEach(async product => {
            let resBool = false
            const resDates = []
            const productDates = productInfo.filter(p => p.item_id === product)
            // check if there is any reservations in current month
            if(reservations.success == false){
                resBool = false
            }else{
                reservations.forEach(reservation => {
                    if(reservation.item_id == productDates[0].item_id){
                        let from = new Date(reservation.from * 1000)
                        const to = new Date(reservation.to * 1000)
                        let currentDate = new Date()
                        // check if the reservation is in the current month or later months
                        if(to > currentDate){
                            // check if this month is in the reservation
                            resBool = true
                        }
                        // make array of each day in reservation
                        while(from <= to){
                            resDates.push(new Date(from))
                            from.setDate(from.getDate() + 1)
                        }
                    }
                })
            }
            if(searchItems.length > 0){
                if(!searchItems.includes(productDates[0].item_name) && !searchItems.includes(productDates[0].item_name + " (Spærret)")){
                    tableContent += "<tr style='display: none;'>"
                }else{
                    tableContent += "<tr>"
                }
            }
            tableContent += `<td scope='row'>${(resBool) ? "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + " (Spærret)</a>" : "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + "</a>"}</td>`
            let i = 0
            days.forEach(day => {
                const currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + day).slice(-2)
                i = 0
                productDates.forEach((date) => {
                    if (currentDate >= date.fromDate && currentDate <= date.toDate && i == 0) {
                        if(resBool == true){
                            const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                            if(isInReservation){
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'><span style="color:yellow; pointer-events: none;">*</span></td>`
                            }else{
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                            }
                        }else{ 
                            tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                        }
                        i++
                    }
                })
                if (i == 0){
                    // check if date is in closed dates array
                    if(closedDates.includes(currentDate)){
                        tableContent += `<td class='bg-secondary'></td>`
                        return
                    }
                    if(resBool == true){
                        const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                        // if the day is in the reservation
                        if(isInReservation){
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"><span style="color:yellow">*</span></td>`
                        }else{
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                        }
                    }else{
                        tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                    }
                }
            })
            tableContent += "</tr>"
        })
        tBody.innerHTML += tableContent+"</div></tbody>"
    }

    // Constructing data for all items with same product_id
    const singleProduct = (value) => {
        eraseTable.innerHTML = ""
        const products = productInfo.filter(p => p.product_id === value)
        // remove duplicates of same item_id
        const uniqueProducts = [...new Set(products.map(item => item.item_id))]
        uniqueProducts.forEach(async (product) => {
            let resBool = false
            const resDates = []
            const productDates = productInfo.filter(p => p.item_id === product)
            if(reservations.success == false){
                resBool = false
            }else{
                reservations.forEach(reservation => {
                    if(reservation.item_id == productDates[0].item_id){
                        let from = new Date(reservation.from * 1000)
                        const to = new Date(reservation.to * 1000)
                        let currentDate = new Date()
                        // check if the reservation is in the current month or later months
                        if(to > currentDate){
                            // check if this month is in the reservation
                            resBool = true
                        }
                        // make array of each day in reservation
                        while(from <= to){
                            resDates.push(new Date(from))
                            from.setDate(from.getDate() + 1)
                        }
                    }
                })
            }
            if(searchItems.length > 0){
                if(!searchItems.includes(productDates[0].item_name) && !searchItems.includes(productDates[0].item_name + " (Spærret)")){
                    tableContent += "<tr style='display: none;'>"
                }else{
                    tableContent += "<tr>"
                }
            }
            tableContent += `<td>${(resBool) ? "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + " (Spærret)</a>" : "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + "</a>"}</td>`
            let i = 0
            days.forEach(day => {
                const currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + day).slice(-2)
                i = 0
                productDates.forEach((date) => {
                    if (currentDate >= date.fromDate && currentDate <= date.toDate && i == 0) {
                        if(resBool == true){
                            const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                            if(isInReservation){
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'><span style="color:yellow; pointer-events: none;">*</span></td>`
                            }else{
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                            }
                        }else{
                            tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                        }
                        i++
                    }
                })
                if (i == 0){
                    // check if date is in closed dates array
                    if(closedDates.includes(currentDate)){
                        tableContent += `<td class='bg-secondary'></td>`
                        return
                    }
                    if(resBool == true){
                        const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                        if(isInReservation){
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"><span style="color:yellow">*</span></td>`
                        }else{
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                        }
                    }else{
                        tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                    }
                }
            })
            tableContent += "</tr>"
        })
        tBody.innerHTML += tableContent+"</div></tbody>"
    }


    // Constructing data for the table
    const showCustomers = () => {
        // Creating a list of unique customers
        const uniqueCustomers = [...new Set(productInfo.map(item => item.rental_id))].filter(item => item !== undefined)
        eraseTable.innerHTML = ""
        // Sort uniqueCustomers by name
        uniqueCustomers.sort((a, b) => {
            const nameA = productInfo.find(p => p.rental_id === a).cust_name
            const nameB = productInfo.find(p => p.rental_id === b).cust_name
            return nameA.localeCompare(nameB)
        })

        uniqueCustomers.forEach(customer => {
            const custDates = productInfo.filter(cust => cust.rental_id === customer)
            const lastDateInMonth = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + new Date(currentYear, currentMonth + 1, 0).getDate()
            if(custDates[0].fromDate > lastDateInMonth) return
            if(searchItems.length > 0){
                if(!searchItems.includes(custDates[0].cust_name + " " + custDates[0].kontonr)){
                    tableContent += "<tr style='display: none;'>"
                }else{
                    tableContent += "<tr>"
                }
            }
            
            tableContent += `<td scope='row' class='cust-name' id='${custDates[0].cust_id}'><span class="searchItem" id='${custDates[0].cust_id}'>${custDates[0].cust_name} ${custDates[0].kontonr} <br>stand: ${custDates[0].item_name}</span></td>`
            let i = 0
            days.forEach(day => {
                const currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + day).slice(-2)
                i = 0
                custDates.forEach((date) => {
                    if (currentDate >= date.fromDate && currentDate <= date.toDate && i == 0) {
                        if(date.item_reserved == 1){
                            tableContent += `<td class='bg-danger redirect' id='${date.rental_id}'><span style="color:yellow; pointer-events: none;">*</span></td>`
                        }else{
                            tableContent += `<td class='bg-danger redirect' id='${date.rental_id}'></td>`
                        }
                        i++
                    }
                })
                if (i == 0){
                    if(closedDates.includes(currentDate)){
                        tableContent += `<td class='bg-secondary'></td>`
                        return
                    }
                    if(custDates[0].item_reserved == 1){
                        tableContent += `<td class='bg-success'><span style="color:yellow">*</span></td>`
                    }else{
                        tableContent += `<td class='bg-success'></td>`
                    }
                }
            })
            tableContent += "</tr>"
        })
        tBody.innerHTML += tableContent+"</div></tbody>"
    }

    // work in progress
    const showAvailability = (availableItems, itemsInfo = null) => {
        // Helper function to strip out content within parentheses
        const stripParentheses = (str) => str.replace(/\(.*?\)/g, '').trim()
        // Sort availableItems with numeric items first and items with letters last
        availableItems.sort((a, b) => {
            const isNumeric = (str) => /^\d+$/.test(str)

            const strippedA = stripParentheses(a.item_name)
            const strippedB = stripParentheses(b.item_name)

            const aIsNumeric = isNumeric(strippedA)
            const bIsNumeric = isNumeric(strippedB)

            // If both items are numeric, compare them numerically
            if (aIsNumeric && bIsNumeric) {
                return parseInt(strippedA, 10) - parseInt(strippedB, 10)
            }

            // If one item is numeric and the other isn't, the numeric one comes first
            if (aIsNumeric) {
                return -1
            }
            if (bIsNumeric) {
                return 1
            }

            // If neither item is numeric, compare them as strings
            return strippedA.localeCompare(strippedB)
        })

        // check if itemsInfo is not null
        if(itemsInfo != null){
            productInfo = itemsInfo
        }

        // Creating a list of unique products
        const thLink = document.querySelectorAll(".th-link")
        const days = getDaysInMonth(currentYear, currentMonth)

        // remove onclick event from th-link and class link-primary and add searchItem class
        thLink.forEach((link, index) => {
            const day = days[index]
            let firstDate = new Date(currentYear+"-"+(currentMonth+1)+"-"+day)
            let dayNumber = firstDate.getDay()
            link.innerHTML = `<th scope='col' role="button" class="link-primary th-link" title="se ledige stande d.${day} ${months[currentMonth]}" onclick='availableOnDay("${currentYear}-${(currentMonth+1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}", ${JSON.stringify(productInfo)}, "${value}")'>${(screenWidth < 1920) ? daysSingleChar[dayNumber] : daysChar[dayNumber]}<br>${day}</th>`
            link.removeAttribute("onclick")
            link.classList.remove("link-primary")
            link.style.cursor = "default"
        })
        const uniqueProducts = [...new Set(availableItems.map(item => item.item_id))]
        eraseTable.innerHTML = ""
        uniqueProducts.forEach(async product => {
            let resBool = false
            const resDates = []
            const productDates = productInfo.filter(p => p.item_id == product)
            if(reservations.success == false){
                resBool = false
            }else{
                reservations.forEach(reservation => {
                    if(reservation.item_id == productDates[0].item_id){
                        let from = new Date(reservation.from * 1000)
                        const to = new Date(reservation.to * 1000)
                        let currentDate = new Date()
                        // check if the reservation is in the current month or later months
                        if(to > currentDate){
                            // check if this month is in the reservation
                            resBool = true
                        }
                        // make array of each day in reservation
                        while(from <= to){
                            resDates.push(new Date(from))
                            from.setDate(from.getDate() + 1)
                        }
                    }
                })
            }
            if(searchItems.length > 0){
                if(!searchItems.includes(productDates[0].item_name) && !searchItems.includes(productDates[0].item_name + " (Spærret)")){
                    tableContent += "<tr style='display: none;'>"
                }else{
                    tableContent += "<tr>"
                }
            }
            tableContent += `<td>${(resBool) ? "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + " (Spærret)</a>" : "<a class='searchItem' href='index.php?singleItem=" + product + "'>" + productDates[0].item_name + "</a>"}</td>`

            let i = 0
            days.forEach(day => {
                const currentDate = currentYear + "-" + ("0" + (currentMonth + 1)).slice(-2) + "-" + ("0" + day).slice(-2)
                i = 0
                productDates.forEach((date) => {
                    if (currentDate >= date.fromDate && currentDate <= date.toDate && i == 0) {
                        if(resBool == true){
                            const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                            if(isInReservation){
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'><span style="color:yellow; pointer-events: none;">*</span></td>`
                            }else{
                                tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                            }
                        }else{
                            tableContent += `<td class='bg-danger redirect' title='${date.cust_name + " " + date.kontonr}' id='${date.rental_id}'></td>`
                        }
                        i++
                    }
                })
                if (i == 0){
                    if(closedDates.includes(currentDate)){
                        tableContent += `<td class='bg-secondary'></td>`
                        return
                    }
                    if(resBool == true){
                        const isInReservation = resDates.some(resDate => resDate.getMonth() == currentMonth && resDate.getDate() == day && resDate.getFullYear() == currentYear)
                        if(isInReservation){
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"><span style="color:yellow">*</span></td>`
                        }else{
                            tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                        }
                    }else{
                        tableContent += `<td class='bg-success' title="start en bookning af stand ${productDates[0].item_name} fra d. ${day} ${months[currentMonth]}" role="button" onclick="window.location.href = 'booking.php?item=${productDates[0].item_id}&time=${currentYear + "-" + (currentMonth+1) + "-" + day}'"></td>`
                    }
                }
            })
            tableContent += "</tr>"
        })
        tBody.innerHTML += tableContent+"</div></tbody>"
    }
    // Choosing which table to show
    if(value == "one"){
        initialTable()
    }else if(value == "two"){
        showCustomers()
    }else if(value == "available"){
        let fromDate, toDate
        // ask the user for a date
        if(availableItems == "" || availableItems == undefined){
            const showModal = document.querySelector(".show-modal")
            const from = document.querySelector(".from")
            const to = document.querySelector(".to")
            from.value = ""
            to.value = ""
            showModal.click()
            flatpickr(".from", {
                altInput: true,
                altFormat: "j F Y",
                dateFormat: "Y-m-d",
                theme: "dark",
                locale: "da",
                minDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    const date = dateStr.split("-")
                    currentMonth = parseInt(date[1]) - 1
                    currentYear = date[0]
                    span.innerHTML = ""
                    span.appendChild(document.createTextNode(months[currentMonth] + " - " + currentYear))
                    fromDate = date[0] + "-" + date[1] + "-" + date[2]

                    // Update the minDate of the "to" flatpickr instance
                    toPicker.set('minDate', dateStr)
                }
            })
            const toPicker = flatpickr(".to", {
                altInput: true,
                altFormat: "j F Y",
                dateFormat: "Y-m-d",
                theme: "dark",
                locale: "da",
                minDate: "today",
                onChange: function(selectedDates, dateStr, instance) {
                    const date = dateStr.split("-")
                    toDate = date[0] + "-" + date[1] + "-" + date[2]
                }
            })
            const modalButton = document.querySelector(".find-available")
            modalButton.addEventListener("click", async (e) => {
                // close modal
                const closeModal = document.querySelector(".close-modal")
                closeModal.addEventListener("click", (e) => {
                    e.preventDefault()
                })
                closeModal.click()
                const standType = document.querySelector(".available-select").value
                
                if(standType == "all"){
                    // Get ALL rental items (not just booked ones)
                    const items = await getProductInfos(currentMonth+1, currentYear)

                    if(items.success == false){
                        alert(items.msg)
                        window.location.href = "index.php?vare"
                        return
                    }
                    
                    // Get all unique items
                    const allUniqueItems = new Map()
                    
                    items.forEach(item => {
                        if (!allUniqueItems.has(item.item_id)) {
                            allUniqueItems.set(item.item_id, {
                                product_name: item.product_name,
                                item_name: item.item_name,
                                product_id: item.product_id,
                                item_id: item.item_id
                            })
                        }
                    })
                    
                    // Convert to itemsInfo format for display
                    itemsInfo = items.map((item) => ({
                        product_name: item.product_name,
                        item_name: item.item_name,
                        fromDate: item.from ? new Date(item.from * 1000).getFullYear() + "-" + ("0" + (new Date(item.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(item.from * 1000).getDate()).slice(-2) : null,
                        toDate: item.to ? new Date(item.to * 1000).getFullYear() + "-" + ("0" + (new Date(item.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(item.to * 1000).getDate()).slice(-2) : null,
                        product_id: item.product_id,
                        cust_id: item.cust_id,
                        rental_id: item.rental_id,
                        cust_name: item.cust_name,
                        item_id: item.item_id,
                        kontonr: item.kontonr
                    }))
                    
                    // Find available items (items without bookings during the selected period)
                    availableItems = []
                    
                    allUniqueItems.forEach((itemDetails, itemId) => {
                        // Get all bookings for this specific item
                        const itemBookings = items.filter(item => 
                            item.item_id === itemId && 
                            item.rental_id && // Only consider actual bookings (not empty entries)
                            item.from && item.to // Make sure booking has dates
                        )
                        
                        // Check reservations for this item
                        let hasReservationDuringPeriod = false
                        if (reservations.success !== false) { // Changed this condition
                            hasReservationDuringPeriod = reservations.some(reservation => {
                                if (parseInt(reservation.item_id) === parseInt(itemId)) {
                                    const reservationFrom = new Date(reservation.from * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.from * 1000).getDate()).slice(-2)
                                    const reservationTo = new Date(reservation.to * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.to * 1000).getDate()).slice(-2)
                                    return !(reservationTo < fromDate || reservationFrom > toDate)
                                }
                                return false
                            })
                        }
                        
                        if (hasReservationDuringPeriod) {
                            return // Skip this item
                        }
                        
                        // Check if any booking overlaps with our selected period
                        const hasOverlappingBooking = itemBookings.some(booking => {
                            const bookingStart = new Date(booking.from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.from * 1000).getDate()).slice(-2)
                            const bookingEnd = new Date(booking.to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.to * 1000).getDate()).slice(-2)
                            
                            // Check for overlap: booking overlaps if it doesn't end before our period starts
                            // AND doesn't start after our period ends
                            return !(bookingEnd < fromDate || bookingStart > toDate)
                        })
                        
                        // If no overlapping booking and no reservation, this item is available
                        if (!hasOverlappingBooking) {
                            availableItems.push(itemDetails)
                        }
                    })
                    
                } else {
                    // Similar logic for specific product type
                    const items = await getBookingsForItemsByType(standType)
                    
                    if(items.success == false){
                        alert(items.msg)
                        window.location.href = "index.php?vare"
                        return
                    }
                    
                    // Get all unique items for this product type
                    const allUniqueItems = new Map()
                    
                    items.forEach(item => {
                        if (!allUniqueItems.has(item.item_id)) {
                            allUniqueItems.set(item.item_id, {
                                product_name: item.product_name,
                                item_name: item.item_name,
                                product_id: item.product_id,
                                item_id: item.item_id
                            })
                        }
                    })
                    
                    itemsInfo = items.map((item) => ({
                        product_name: item.product_name,
                        item_name: item.item_name,
                        fromDate: item.from ? new Date(item.from * 1000).getFullYear() + "-" + ("0" + (new Date(item.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(item.from * 1000).getDate()).slice(-2) : null,
                        toDate: item.to ? new Date(item.to * 1000).getFullYear() + "-" + ("0" + (new Date(item.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(item.to * 1000).getDate()).slice(-2) : null,
                        product_id: item.product_id,
                        cust_id: item.cust_id,
                        rental_id: item.rental_id,
                        cust_name: item.cust_name,
                        item_id: item.item_id,
                        kontonr: item.kontonr
                    }))

                    availableItems = []
                    
                    allUniqueItems.forEach((itemDetails, itemId) => {
                        // Get all bookings for this specific item
                        const itemBookings = items.filter(item => 
                            item.item_id === itemId && 
                            item.rental_id && 
                            item.from && item.to
                        )
                        
                        // Check reservations
                        let hasReservationDuringPeriod = false
                        if (reservations.success !== false) { // Changed this condition
                            hasReservationDuringPeriod = reservations.some(reservation => {
                                if (parseInt(reservation.item_id) === parseInt(itemId)) {
                                    const from = new Date(reservation.from * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.from * 1000).getDate()).slice(-2)
                                    const to = new Date(reservation.to * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.to * 1000).getDate()).slice(-2)
                                    return !(to < fromDate || from > toDate)
                                }
                                return false
                            })
                        }
                        
                        if (hasReservationDuringPeriod) {
                            return // Skip this item
                        }
                        
                        const hasOverlappingBooking = itemBookings.some(booking => {
                            const bookingStart = new Date(booking.from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.from * 1000).getDate()).slice(-2)
                            const bookingEnd = new Date(booking.to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.to * 1000).getDate()).slice(-2)
                            
                            return !(bookingEnd < fromDate || bookingStart > toDate)
                        })
                        
                        if (!hasOverlappingBooking) {
                            availableItems.push(itemDetails)
                        }
                    })
                }
                
                showAvailability(availableItems, itemsInfo)
            })
        }else{
            showAvailability(availableItems)
        }
    }else{
        singleProduct(value)
    }
    // getting unique products by id
    const uniqueProducts = [...new Set(productInfo.map(item => item.product_id))]
    const extraOptions = []
    uniqueProducts.forEach(product => {
        const productDates = productInfo.filter(p => p.product_id === product)
        extraOptions.push({ value: productDates[0].product_id, text: productDates[0].product_name })
    })

    // sort extraOptions by text
    extraOptions.sort((a, b) => a.text.localeCompare(b.text))
    
    // Creating the select element
    const optionData = [
        { value: "one", text: "Produkter" },
        { value: "two", text: "Kunder" },
        { value: "available", text: "Ledige"},
        ...extraOptions
    ]

    const select = createSelect(optionData, value)
    
    //add all optionData to the select element
    const availableSelect = document.querySelector(".available-select")
    availableSelect.innerHTML = ""
    const optionElement = document.createElement("option")
    optionElement.setAttribute("value", "all")
    optionElement.setAttribute("class", "option")
    optionElement.appendChild(document.createTextNode("Alle"))
    availableSelect.appendChild(optionElement)
    extraOptions.forEach(option => {
        const optionElement = document.createElement("option")
        optionElement.setAttribute("value", option.value)
        optionElement.setAttribute("class", "option")
        optionElement.appendChild(document.createTextNode(option.text))
        availableSelect.appendChild(optionElement)
    })


    // Adding eventListener to the select element
    select.addEventListener("change", (e) => {
        productOverviewMonth(thisMonth, year, e.target.value)
    }) 

/*     select.addEventListener("click", (e) => {
        // empty the availableItems array
        availableItems.splice(0, availableItems.length)
    }) */

    // Appending the select element to the DOM
    const classSelector = document.querySelector(".select")
    classSelector.appendChild(select)
    const input = document.createElement("input")
    input.setAttribute("type", "text")
    input.setAttribute("class", "form-control")
    input.setAttribute("placeholder", "Søg.. Separere med komma (,) hvis du vil søge efter flere produkter")
    input.setAttribute("title", "Separere med komma (,) hvis du vil søge efter flere produkter")
    input.setAttribute("id", "search")
    // check if searchInput is not empty or undefined and add it to the input element
    if(searchInput != "" && searchInput != undefined) input.setAttribute("value", searchInput)
    classSelector.appendChild(input)

    // Adding eventListener to the search input
    const search = document.querySelector("#search")
    search.addEventListener("keyup", (e) => {
        // empty the searchItems array
        searchItems.splice(0, searchItems.length)

        // search for multiple words separated by comma
        const values = e.target.value.toLowerCase().split(",")
        searchInput = e.target.value
        const items = document.querySelectorAll(".searchItem")
        items.forEach(item => {
            if(searchInput == ""){
                item.parentElement.parentElement.style.display = ""
                return
            }
            let check = false
            values.forEach(word => {
                if(value == "two"){
                    if(item.innerHTML.toLowerCase().includes(word.trim())) check = true
                }else{
                    const itemName = item.innerHTML.split(" ")[0]
                    if(itemName.toLowerCase() === word.trim()){
                        check = true
                    }
                }
            })
            if(check){
                item.parentElement.parentElement.style.display = ""
                if(values[0] != "") searchItems.push(item.innerHTML)
            }else{
                item.parentElement.parentElement.style.display = "none"
            }
        })
    })

    // Adding eventListener to the clickable tds
    const clickableTds = document.querySelectorAll(".redirect")
    clickableTds.forEach(button => {
        button.addEventListener("click", async (e) => {
            const id = e.target.id
            window.location.href = `index.php?showBooking=${id}`
        })
    })

    // Adding eventListener to the clickable tds
    const custName = document.querySelectorAll(".cust-name")
    custName.forEach(name => {
        name.addEventListener("click", async (e) => {
            const id = e.target.id
            window.location.href = `index.php?showCustomer=${id}`
        })
    })
    document.dispatchEvent(new CustomEvent('renderComplete'))
}

const createSelect = (options, value) => {
    // Creating the select element
    const select = document.createElement("select")
    select.setAttribute("class", "transparent")

    options.forEach(option => {
        const optionElement = document.createElement("option")
        optionElement.setAttribute("value", option.value)
        optionElement.setAttribute("class", "option")
        if (value == option.value) {
            optionElement.setAttribute("selected", "selected")
        }
        optionElement.appendChild(document.createTextNode(option.text))
        select.appendChild(optionElement)
    })

    return select
}



const showBooking = async () => {
    // Hidding some elements
    document.querySelector(".backward").hidden = true
    document.querySelector(".forward").hidden = true

    document.querySelector(".table-point").innerHTML = "<table class='table table-responsive-sm table-light'><tbody class='tBody'></tbody></table>"
    const tBody = document.querySelector(".tBody")
    const urlParams = new URLSearchParams(queryString)
    const bookingId = urlParams.get("showBooking")
    const booking = await getBooking(bookingId)
    const item = await getItem(booking.item_id)
    const bookingInfo = {
        name: booking.name,
        account_number: booking.account_number,
        fromDate: new Date(booking.from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.from * 1000).getDate()).slice(-2),
        toDate: new Date(booking.to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.to * 1000).getDate()).slice(-2),
        item_name: item.item_name,
        cust_id: booking.customer_id,
        rental_id: booking.id,
        orderStatus: booking.status
    }

    const table = document.querySelector(".table-point")

    // Create the table
    let newTable = "<tr><th>Udlejnings Product</th><th>Navn</th><th>Konto Nr</th><th>Fra</th><th>Til</th><th>Redigere</th><th>Slet</th></tr>"
    newTable += "<tr>"
    newTable += `<td>${bookingInfo.item_name}</td>`
    newTable += `<td>${bookingInfo.name}</td>`
    newTable += `<td>${bookingInfo.account_number}</td>`
    newTable += `<td>${bookingInfo.fromDate}</td>`
    newTable += `<td>${bookingInfo.toDate}</td>`
    newTable += `<td><a href="edit.php?id=${bookingInfo.rental_id}"><button class="btn btn-primary">${editIcon}</button></a></td>`
    newTable += `<td><button class="btn btn-danger delete" id="${bookingInfo.rental_id}">${deleteIcon}</button></td>`
    newTable += "</tr>"
    newTable += "</tbody></table>"
    tBody.innerHTML = newTable
    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            if(bookingInfo.orderStatus == 1){
                confirm("Er du sikker på du vil slette en faktureret booking?")
                const id = e.target.id
                const response = await deleteBooking(id)
                alert(response)
                window.location.href = "index.php?vare"
            }else{
                if(confirm ("Er du sikker på at du vil slette denne udlejning?") == false) return
                const id = e.target.id
                const response = await deleteBooking(id)
                alert(response)
                window.location.href = "index.php?vare"
            }
        })
    })
}

const singleProductOverview = async (id) => {
    const createTable = (productInfo) => {
        const tBody = document.querySelector(".tBody")
        let tableContent = ""

        // Constructing the table headers with sort icons
        let tableHeader = `<tr>
        <th class="sort" id="name">${(sortName) ? sortDown : sortUp} Navn</th>
        <th class="sort" id="accountNr">${(sortAccount) ? sortDown : sortUp} Konto Nr</th>
        <th class="sort" id="from">${(sortFrom) ? sortDown : sortUp} Fra</th>
        <th class="sort" id="to">${(sortTo) ? sortDown : sortUp} Til</th>
        <th class="sort" id="edit">Redigere</th>
        <th class="sort" id="delete">Slet</th>
        </tr>`
        tableContent = tableHeader

        productInfo.forEach(booking => {
            tableContent += "<tr>"
            tableContent += `<td>${booking.name}</td>`
            tableContent += `<td>${booking.account_number}</td>`
            tableContent += `<td>${booking.fromDate}</td>`
            tableContent += `<td>${booking.toDate}</td>`
            tableContent += `<td><a href="edit.php?id=${booking.id}"><button class="btn btn-primary">${editIcon}</button></a></td>`
            tableContent += `<td><button class="btn btn-danger delete" id="${booking.id}">${deleteIcon}</button></td>`
            tableContent += "</tr>"
        })
        tBody.innerHTML = tableContent

        // Adding eventListener to the table headers
        setTimeout(() => {
        const sort = document.querySelectorAll(".sort")
        sort.forEach(header => {
            header.addEventListener("click", async (e) => {
                const id = e.target.id
                if (id === "name") {
                    sortName = !sortName
                    sortAccount = false
                    sortFrom = false
                    sortTo = false
                } else if (id === "accountNr") {
                    sortName = false
                    sortAccount = !sortAccount
                    sortFrom = false
                    sortTo = false
                } else if (id === "from") {
                    sortName = false
                    sortAccount = false
                    sortFrom = !sortFrom
                    sortTo = false
                } else if (id === "to") {
                    sortName = false
                    sortAccount = false
                    sortFrom = false
                    sortTo = !sortTo
                }

                // Sorting the table data by the selected header asc and desc
                
                if (id === "name") {
                    sortNameAsc = !sortNameAsc
                    productInfo.sort((a, b) => (sortNameAsc ? 1 : -1) * a.name.localeCompare(b.name))
                  } else if (id === "accountNr") {
                    sortAccountAsc = !sortAccountAsc
                    productInfo.sort((a, b) => (sortAccountAsc ? 1 : -1) * a.account_number.localeCompare(b.account_number))
                  } else if (id === "from") {
                    sortFromAsc = !sortFromAsc
                    productInfo.sort((a, b) => (sortFromAsc ? 1 : -1) * (new Date(a.fromDate) - new Date(b.fromDate)))
                  } else if (id === "to") {
                    sortToAsc = !sortToAsc
                    productInfo.sort((a, b) => (sortToAsc ? 1 : -1) * (new Date(a.toDate) - new Date(b.toDate)))
                  }
                createTable(productInfo)
            })
        })
    }, 500)
    }

    

    // Hidding some elements
    document.querySelector(".backward").hidden = true
    document.querySelector(".forward").hidden = true
    
    const fullProductInfo = []
    const bookings = await getItemBookings(id)
    const item = await getItem(id)
    const table = document.querySelector(".table-point")
    table.setAttribute("class", "table-point text-center")
    table.innerHTML = "<table class='table table-responsive-sm table-light table-striped mt-4'><tbody class='tBody'></tbody></table>"
    const span = document.querySelector(".month")
    document.querySelector(".flex-content").setAttribute("class", "text-center")
    span.appendChild(document.createTextNode(`Stand: ${item.item_name}`))
    let sortName = false
    let sortAccount = false
    let sortFrom = false
    let sortTo = false
    let sortNameAsc = false
    let sortAccountAsc = false
    let sortFromAsc = false
    let sortToAsc = false
    // Constructing data for the table
    if(bookings.success == false){
        table.innerHTML = `<p class='text-center mt-4 mb-4'>${bookings.msg}</p>`
    }else{
        const productInfo = bookings.map((booking) => ({
            id: booking.id,
            name: booking.name,
            account_number: booking.account_number,
            fromDate: new Date(booking.from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.from * 1000).getDate()).slice(-2),
            toDate: new Date(booking.to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.to * 1000).getDate()).slice(-2),
        }))
        // Make the latest booking show first
        productInfo.sort((a, b) => -1 * (new Date(a.fromDate) - new Date(b.fromDate)))
        // reduce productInfo to 5 items
        fullProductInfo.push(...productInfo)
        if(productInfo.length > 5){
            productInfo.length = 5
        }
        createTable(productInfo)

    }



    const a = document.createElement("a")
    a.setAttribute("href", "booking.php?item=" + item.id)
    a.setAttribute("class", "btn btn-primary mt-4 mb-4 mx-auto")
    a.appendChild(document.createTextNode("Opret ny udlejning"))
    const showAll = document.createElement("button")
    showAll.setAttribute("class", "btn btn-primary mt-4 mb-4 mx-auto showAll")
    showAll.appendChild(document.createTextNode("Vis alle udlejninger"))
    const showAllDiv = document.createElement("div")
    showAllDiv.setAttribute("class", "w-100 text-center mt-4 mb-4")
    showAllDiv.appendChild(showAll)
    table.appendChild(showAllDiv)
    table.appendChild(a)
    const hr = document.createElement("hr")
    hr.setAttribute("class", "mt-5 mb-5")
    table.appendChild(hr)
    const reservations = await getReservationsByItem(id)
    setTimeout(() => {
        const showAll = document.querySelector(".showAll")
        showAll.addEventListener("click", async () => {
            if(showAll.innerHTML == "Vis alle udlejninger"){
                createTable(fullProductInfo)
                showAll.innerHTML = "Vis færre udlejninger"
            } else {
                const productInfo = fullProductInfo
                productInfo.length = 5
                createTable(productInfo)
                showAll.innerHTML = "Vis alle udlejninger"
            }
        })
    }, 500)
    let productDates
    if(reservations.success == false){
        table.innerHTML += `<p class='text-center mt-4'>${reservations.msg}</p>`
    }else{
        productDates = reservations.map((reservation) => ({
            id: reservation.id,
            text: reservation.text,
            fromDate: new Date(reservation.from * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.from * 1000).getDate()).slice(-2),
            toDate: new Date(reservation.to * 1000).getFullYear() + "-" + ("0" + (new Date(reservation.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(reservation.to * 1000).getDate()).slice(-2),
        }))
        const list = document.createElement("table")
        list.setAttribute("class", "table table-responsive-sm table-light table-striped mt-5")
        list.innerHTML = "<thead><tr><th>Spærret fra</th><th>Spærret til</th><th>Kommentar</th><th>Redigere tekst</th><th>Redigere datoer</th><th>Slet</th></tr></thead><tbody class='reservations'></tbody>"
        table.appendChild(list)
        const reservationList = document.querySelector(".reservations")
        let reservationContent = ""
        productDates.forEach(date => {
            reservationContent += "<tr>"
            reservationContent += `<td>${date.fromDate}</td>`
            reservationContent += `<td>${date.toDate}</td>`
            reservationContent += `<td>${(date.text == null) ? "" : date.text}</td>`
            reservationContent += `<td><button class="btn btn-primary editComment" id="${date.id}">${editIcon}</button></a></td>`
            reservationContent += `<td><button class="btn btn-primary editDates" id="${date.id}">${editIcon}</button></a></td>`
            reservationContent += `<td><button class="btn btn-danger delete-res" id="${date.id}">${deleteIcon}</button></td>`

            reservationContent += "</tr>"
        })
        reservationList.innerHTML = reservationContent
    }

    const deleteButtons = document.querySelectorAll(".delete")
        deleteButtons.forEach(button => {
            button.addEventListener("click", async (e) => {
                if(confirm ("Er du sikker på at du vil slette denne udlejning?") == false) return
                const id = e.target.id
                const response = await deleteBooking(id)
                alert(response)
                window.location.href = "index.php?singleItem=" + item.id
            })
        })

    const editComment = document.querySelectorAll(".editComment")
    editComment.forEach(button => {
        button.addEventListener("click", async (e) => {
            const id = e.target.id
            const text = prompt("Skriv en kommentar til spærringen (vis du ikke ønsker kommentar lad feltet være tomt)", "")
            const data = {
                id: id,
                text: text
            }
            const result = await editReservationComment(data)
            alert(result)
            // refresh page
            window.location.href = "index.php?singleItem=" + item.id
        })
    })

    function isValidDate(dateString) {
        const regex = /^\d{4}-\d{2}-\d{2}$/
    
        if (!regex.test(dateString)) {
            // The date string is not in the correct format
            return false
        }
    
        // Parse the date parts
        const parts = dateString.split('-')
        const year = parseInt(parts[0], 10)
        const month = parseInt(parts[1], 10)
        const day = parseInt(parts[2], 10)
    
        // Check the ranges of month and year
        if (month <= 0 || month > 12) {
            return false
        }
    
        const date = new Date(year, month - 1, day)
        // Check it is a valid date and is the same value as the parts
        if (!(date.getFullYear() === year && date.getMonth() + 1 === month && date.getDate() === day)) {
            return false
        }
    
        return true
    }

    const editDates = document.querySelectorAll(".editDates")
    editDates.forEach(button => {
        button.addEventListener("click", async (e) => {
            const id = e.target.id
            const showModal = document.querySelector(".show-modal2")
            const from = document.querySelector(".from-two")
            const to = document.querySelector(".to-two")
            showModal.click()
            let fromDate, toDate
            flatpickr(from, {
                dateFormat: 'Y-m-d',
                theme: "dark",
                locale: "da",
                onChange: (selectedDates, dateStr, instance) => {
                fromDate = dateStr
                  const [year, month, day] = dateStr.split('-').map(Number)
                  fromDate =  new Date(year, month - 1, day)
                  // if from date is before a date in bookedDates then disable all dates after that date
                  flatpickr(to, {
                    dateFormat: 'Y-m-d',
                    minDate: fromDate,
                    theme: "dark",
                    locale: "da",
                    onDayCreate: function(dObj, dStr, fp, dayElem) {
                      if (settings.find_weeks === "1" && fromDate != undefined && fromDate != "" && fromDate != "Invalid Date") {
                        const date = new Date(dayElem.dateObj)
                        const dateStr = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
                        const timeDifference = Math.abs(date - fromDate)
                        const daysDifference = Math.round(timeDifference / (1000 * 60 * 60 * 24)) + 1
                        if(daysDifference % 7 === 0 && daysDifference !== 0 && daysDifference !== 1){
                          dayElem.className += " has-action"
                        }
                      }
                    },
                    onChange: (selectedDates, dateStr, instance) => {
                    toDate = dateStr
                      if(toDate === undefined || toDate === "" || fromDate === undefined || fromDate === "" || toDate === "Invalid Date" || fromDate === "Invalid Date"){
                        return
                      }
                    }
                  })
                  if(toDate === undefined || toDate === "" || fromDate === undefined || fromDate === "" || toDate === "Invalid Date" || fromDate === "Invalid Date"){
                    return
                  }
                }
            })
            const changeReservation = document.querySelector(".change-reservation")
            changeReservation.addEventListener("click", async () => {
                if(fromDate == "" || toDate == ""){
                    alert("Kunne ikke ændre spærring")
                }
                const closeModal = document.querySelector(".close-modal2")
                closeModal.click()
                const data = {
                    id: id,
                    from: new Date(fromDate)/1000,
                    to: new Date(toDate)/1000
                }
                const result = await editReservationDates(data)
            alert(result)
            // refresh page
            window.location.href = "index.php?singleItem=" + item.id
            })
            /* const product = productDates.find(p => p.id == id)
            if(fromDate == "" && toDate == ""){
                alert("Der blev ikke udfyldt nogen dato så intet er ændret")
                return
            }else if(fromDate != "" && !isValidDate(fromDate) || (toDate != "" && !isValidDate(toDate))){
                alert("Du har skrevet en dato i forkert format")
                return
            }

            const data = {
                id: id,
                from: new Date(product.fromDate)/1000,
                to: new Date(product.toDate)/1000
            }

            if(fromDate != ""){
                fromDate = new Date(fromDate)/1000
                if(toDate != ""){
                    toDate = new Date(toDate)/1000
                    if(fromDate > toDate){
                        alert("Du kan ikke sætte start dato efter slut dato")
                        return
                    }else{
                        data.from = fromDate
                        data.to = toDate
                    }
                }else{
                    if(fromDate > data.to){
                        alert("Du kan ikke sætte start dato efer slut dato")
                        return
                    }else{
                        data.from = fromDate
                    }
                }
            }else{
                toDate = new Date(toDate)/1000
                if(toDate < data.from){
                    alert("Du kan ikke sætte slut dato før start dato")
                    return
                }else{
                    data.to = toDate
                }
            }

            const result = await editReservationDates(data)
            alert(result)
            // refresh page
            window.location.href = "index.php?singleItem=" + item.id */
        })
    })

    const div = document.createElement("div")
    div.setAttribute("class", "row mt-5 spærring")
    div.innerHTML = `
        <h4 class="mt-4 mb-4">Opret Spærrings periode:</h4>
        <div class="form-group col-6 mt-4">
            <label for="from">Start dato:</label>
            <input type="text" class="from">
        </div>
        <div class="form-group col-6 mt-4">
            <label for="to">Slut dato:</label>
            <input type="text" class="to">
        </div>
        <button class="btn btn-primary res col-3 mt-2 mx-auto">Opret Spærring</button>
        `
    table.appendChild(div)
    let fromDate, toDate
    // set fromDate to todays date
    const datePick = flatpickr(".from", {
        dateFormat: 'Y-m-d',
        theme: "dark",
        locale: "da",
        onChange: (selectedDates, dateStr, instance) => {
        fromDate = dateStr
          const [year, month, day] = dateStr.split('-').map(Number)
          fromDate =  new Date(year, month - 1, day)
          // if from date is before a date in bookedDates then disable all dates after that date
          flatpickr(".to", {
            dateFormat: 'Y-m-d',
            minDate: fromDate,
            theme: "dark",
            locale: "da",
            onDayCreate: function(dObj, dStr, fp, dayElem) {
              if (settings.find_weeks === "1" && fromDate != undefined && fromDate != "" && fromDate != "Invalid Date") {
                const date = new Date(dayElem.dateObj)
                const dateStr = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
                const timeDifference = Math.abs(date - fromDate)
                const daysDifference = Math.round(timeDifference / (1000 * 60 * 60 * 24)) + 1
                if(daysDifference % 7 === 0 && daysDifference !== 0 && daysDifference !== 1){
                  dayElem.className += " has-action"
                }
              }
            },
            onChange: (selectedDates, dateStr, instance) => {
            toDate = dateStr
              if(toDate === undefined || toDate === "" || fromDate === undefined || fromDate === "" || toDate === "Invalid Date" || fromDate === "Invalid Date"){
                return
              }
            }
          })
          if(toDate === undefined || toDate === "" || fromDate === undefined || fromDate === "" || toDate === "Invalid Date" || fromDate === "Invalid Date"){
            return
          }
        }
      })
/*     flatpickr(".from", {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        theme: "dark",
        locale: "da",
        onChange: (selectedDates, dateStr, instance) => {
            fromDate = dateStr
        }
    })
    flatpickr(".to", {
        dateFormat: 'Y-m-d',
        minDate: "today",
        theme: "dark",
        locale: "da",
        onChange: (selectedDates, dateStr, instance) => {
            toDate = dateStr
        }
    }) */

    const res = document.querySelector(".res")
    res.addEventListener("click", async () => {
        // check if there is already a reservation after todays date
        const text = prompt("Skriv en kommentar til spærringen (vis du ikke ønsker kommentar lad feltet være tomt)", "")
        const today = new Date()
        if(reservations.success != false){
            for (let r of reservations) {
                const toDate = new Date(r.to * 1000)
                if (toDate > today) {
                    alert("Der er allerede en spærring efter dags dato")
                    return
                }
            }
        }
        const data = {
            item_id: id,
            from: new Date(fromDate)/1000,
            to: new Date(toDate)/1000,
            text: text
        }
        const result = await createReservation(data)
        alert(result)
        // refresh page
        window.location.href = "index.php?singleItem=" + item.id
    })

    const deleteRes = document.querySelectorAll(".delete-res")
    deleteRes.forEach(button => {
        button.addEventListener("click", async (e) => {
            if(confirm ("Er du sikker på at du vil slette denne spærring?") == false) return
            const id = e.target.id
            const response = await deleteReservation(id)
            alert(response)
            window.location.href = "index.php?singleItem=" + item.id
        })
    })
    document.dispatchEvent(new CustomEvent('renderComplete'))
}

const showCustomer = async () => {
    // Hidding some elements
    document.querySelector(".backward").hidden = true
    document.querySelector(".forward").hidden = true

    document.querySelector(".table-point").innerHTML = "<table class='table table-responsive-sm table-light'><tbody class='tBody'></tbody></table>"
    const tBody = document.querySelector(".tBody")
    const urlParams = new URLSearchParams(queryString)
    const bookingId = urlParams.get("showCustomer")
    const booking = await getBookingByCustomer(bookingId)
    const bookingInfo = booking.map((booking) => ({
        name: booking.name,
        account_number: booking.account_number,
        fromDate: new Date(booking.from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.from * 1000).getDate()).slice(-2),
        toDate: new Date(booking.to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.to * 1000).getDate()).slice(-2),
        item_name: booking.item_name,
        cust_id: booking.customer_id,
        rental_id: booking.id,
        orderStatus: booking.status
    }))
    // sort bookingInfo by date descending
    bookingInfo.sort((a, b) => (new Date(b.fromDate) - new Date(a.fromDate)))

    const table = document.querySelector(".table-point")

    // Create the table
    let newTable = "<tr><th>Udlejnings Product</th><th>Navn</th><th>Konto Nr</th><th>Fra</th><th>Til</th><th>Redigere</th><th>Slet</th></tr>"
    
    bookingInfo.forEach(bookingInfo => {
        newTable += "<tr>"
        newTable += `<td>${bookingInfo.item_name}</td>`
        newTable += `<td>${bookingInfo.name}</td>`
        newTable += `<td>${bookingInfo.account_number}</td>`
        newTable += `<td>${bookingInfo.fromDate}</td>`
        newTable += `<td>${bookingInfo.toDate}</td>`
        newTable += `<td><a href="edit.php?id=${bookingInfo.rental_id}"><button class="btn btn-primary">${editIcon}</button></a></td>`
        newTable += `<td><button class="btn btn-danger delete" id="${bookingInfo.rental_id}">${deleteIcon}</button></td>`
        newTable += "</tr>"
        newTable += "</tbody></table>"
    })

    tBody.innerHTML = newTable

    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            if(confirm ("Er du sikker på at du vil slette denne udlejning?") == false) return
            const id = e.target.id
            const response = await deleteBooking(id)
            alert(response)
            window.location.href = "index.php?vare"
        })
    })
}

// Init the view
if (queryString !== "") {
    const urlParams = new URLSearchParams(queryString)
    if (urlParams.has("vare")) {
        /* if (urlParams.has("month") && urlParams.has("year")) {
            const month = urlParams.get("month")
            const year = urlParams.get("year") */
            const month = new Date().getMonth()
            const year = new Date().getFullYear()
            productOverviewMonth(month, year, "one")
        /* } else { */
            /* productOverview("one") */
       /*  } */
    } else if (urlParams.has("day") && urlParams.has("month") && urlParams.has("year") && urlParams.has("value")) {
        const day = urlParams.get("day")
        const month = urlParams.get("month")
        const year = urlParams.get("year")
        const value = urlParams.get("value")
        createReservationList(year, month, day, value)
    } else if (urlParams.has("month") && urlParams.has("year")) {
        const month = urlParams.get("month")
        const year = urlParams.get("year")
        monthlyOverview(year, month)
    } else if (urlParams.has("showBooking")) {
        showBooking()
    }else if (urlParams.has("showCustomer")) {
        showCustomer()
    }else if(urlParams.has("singleItem")){
        const value = urlParams.get("singleItem")
        singleProductOverview(value)
    }
} else {
    createCalendar()
}
})()

