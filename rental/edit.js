// Make sure you're in an async context
(async () => {
  const url = new URL(window.location.href)
  const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
  const firstFolder = pathSegments[0]
  // Dynamically import the module
  const { getBooking, updateBooking, updateItem, getAllItemsFromId ,deleteItem, createItem, getClosedDays, getItemBookings, getSettings, updateOrder } = await import(`/${firstFolder}/rental/api/api.js`)

const settings = await getSettings()

const findClosedDates = (closedDays, fromDate, toDate) => {
    const closedDates = []
  
    closedDays.forEach(closedDay => {
      const closedDate = new Date(closedDay.date * 1000)
      if (closedDate >= fromDate && closedDate <= toDate) {
        closedDates.push(formatDate(closedDate))
      }
    })
  
    return closedDates
  }

  const formatDate = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  
  // Function to adjust the booking end date to avoid closed dates
  const adjustBookingEndDateIfNeeded = async (fromDate, toDate) => {
    const closedDays = await getClosedDays()
    if(closedDays.success === false){
      return toDate
    }
    const fromDateData = new Date(fromDate)
    const toDateData = new Date(toDate)
  
    const closedDatesInRange = findClosedDates(closedDays, fromDateData, toDateData)
  
    if (closedDatesInRange.length > 0) {
      closedDatesInRange.forEach(closedDate => {
        toDateData.setDate(toDateData.getDate() + 1)
      })
  
      return formatDate(toDateData)
    } else {
      return toDate // Return the original toDate if there are no closed dates in the range
    }
  }


const createInputElement = (type, name, className, value, id=0) => {
    const input = document.createElement("input")
    input.setAttribute("type", type)
    input.setAttribute("name", name)
    input.setAttribute("class", className)
    input.setAttribute("id", id)
    input.setAttribute("value", value)
    return input
}

const createButtonElement = (type, className, value, id=0) => {
    const button = document.createElement("button")
    button.setAttribute("type", type)
    button.setAttribute("class", className)
    button.setAttribute("id", id)
    button.textContent = value
    return button
}


const createLabelAndDivElement = (className, labelText, element, labelClass="") => {
    const div = document.createElement("div")
    const label = document.createElement("label")
    label.textContent = labelText
    label.setAttribute("class", labelClass)
    div.setAttribute("class", className)
    div.appendChild(label)
    div.appendChild(element)
    return div
}

const createDivElement = (className, element) => {
    const div = document.createElement("div")
    div.setAttribute("class", className)
    element.forEach(e => {
        div.appendChild(e)
    })
    return div
}

const createHrElement = (className) => {
    const hr = document.createElement("hr")
    hr.setAttribute("class", className)
    return hr
}

const appendToFormGroup = (rootElement, elements) => {
    elements.forEach(element => {
        rootElement.appendChild(element)
    })
}

const editBooking = async (id) => {
    const customer = await getBooking(id)
    let fromDate = new Date(customer.from * 1000)
    let toDate = new Date(customer.to * 1000)
    fromDate = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
    toDate = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
    const form = document.querySelector(".form")
    const row = document.querySelector(".rental-option")
    const name = document.querySelector(".name")
    name.textContent = customer.item_name + " - " + customer.name

    // Create elements for the calendar view
    const inputFrom = createInputElement("text", "fromDate", "form-control from", fromDate)
    const div = createLabelAndDivElement("form-group col-6", "from", inputFrom)
    const inputTo = createInputElement("text", "toDate", "form-control to", toDate)
    const div2 = createLabelAndDivElement("form-group col-6", "to", inputTo)
    const button = createButtonElement("submit", "btn btn-primary col-1", "Opdater")
    appendToFormGroup(row, [div, div2])
    appendToFormGroup(form, [button])

  // get bookings for selected item
  const bookings = await getItemBookings(customer.item_id)
  const dates = []
  if(bookings.msg !== "Der er ingen bookinger"){
    bookings.forEach(b => {
      if(b.id == id) return
      const fromDate = new Date(b.from * 1000)
      const fromDateFormattad = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
      const toDate = new Date(b.to * 1000)
      const toDateFormattad = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
      dates.push([fromDateFormattad, toDateFormattad])
    })
  }

  // get disabled dates from closed days
  const closedDays = await getClosedDays()
  const closedDates = []
  if(closedDays.success !== false){
    closedDays.forEach(i => {
      const date = new Date(i.date * 1000)
      const year = date.getFullYear()
      const month = (date.getMonth() + 1 < 10) ? "0" + (date.getMonth() + 1) : date.getMonth() + 1
      const day = (date.getDate() < 10) ? "0" + date.getDate() : date.getDate()
      const formattedDate = year + "-" + month + "-" + day
      closedDates.push(formattedDate)
    })
  }

  // Helper function to generate dates between two dates
  function generateDates(startDate, endDate) {
    const dates = []
    const currentDate = new Date(startDate)
    const end = new Date(endDate)

    // Normalize the time component to midnight for both dates
    currentDate.setHours(0, 0, 0, 0)
    end.setHours(0, 0, 0, 0)
    
    while (currentDate <= endDate) {
      const year = currentDate.getFullYear()
      const month = (currentDate.getMonth() + 1 < 10) ? "0" + (currentDate.getMonth() + 1) : currentDate.getMonth() + 1
      const day = (currentDate.getDate() < 10) ? "0" + currentDate.getDate() : currentDate.getDate()
      const formattedDate = year + "-" + month + "-" + day
      dates.push(formattedDate)
      
      // Move to the next day
      currentDate.setDate(currentDate.getDate() + 1)
    }
    
    return dates
  }

  // get already booked dates
  const bookedDates = []
  if(dates){
    dates.forEach(d => {
      const [fromDate, toDate] = d
      const fromDateObj = new Date(fromDate)
      const toDateObj = new Date(toDate)
      const dates = generateDates(fromDateObj, toDateObj)
      bookedDates.push(...dates)
    })
  }

  // draw form
  const fromDatePicker = document.querySelector('[name=fromDate]')
  const toDatePicker = document.querySelector('[name=toDate]')
  let toDateData = toDate
  let fromDateData = fromDate
  let addedDays
  const addedDaysArray = []
  closedDates.push(...bookedDates)
  const today = new Date();

  const toPickr = flatpickr(toDatePicker, {
    altInput: true,
    altFormat: "d-m-Y",
    dateFormat: 'Y-m-d',
    minDate: today,
    theme: "dark",
    locale: "da",
    disable: closedDates,
    defaultDate: toDate, // Add this line to set initial value
    onDayCreate: function(dObj, dStr, fp, dayElem) {
      if (settings.find_weeks === "1" && fromDate != undefined && fromDate != "" && fromDate != "Invalid Date") {
        const date = new Date(dayElem.dateObj)
        const dateStr = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
        if(closedDates.includes(dateStr) && !addedDaysArray.includes(dateStr)){
          addedDaysArray.push(dateStr)
        }
        const daysToAdd = addedDaysArray.filter(d => d >= fromDateData && d <= dateStr)
        addedDays = daysToAdd.length
        const timeDifference = Math.abs(date - fromDate)
        const daysDifference = Math.round(timeDifference / (1000 * 60 * 60 * 24)) + 1 - addedDays
        if(daysDifference % 7 === 0 && daysDifference !== 0 && daysDifference !== 1 && closedDates.includes(dateStr) === false){
          dayElem.className += " has-action"
        }
      }
    },
    onChange: (selectedDates, dateStr, instance) => {
      toDateData = dateStr
    }
  })
  

  const datePick = flatpickr(fromDatePicker, {
    altInput: true,
    altFormat: "d-m-Y",
    dateFormat: 'Y-m-d',
    theme: "dark",
    locale: "da",
    disable: closedDates,
    onDayCreate: function(dObj, dStr, fp, dayElem) {
      // Sort bookings in descending order based on the 'to' property
      if(bookings.msg === "Der er ingen bookinger"){
        return
      }

      bookings.sort((a, b) => b.to - a.to)

      // Get the last booking
      const lastBooking = bookings[0]
    
      const date = new Date(dayElem.dateObj)
      const dateStr = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)

      if(closedDates.includes(dateStr) && !addedDaysArray.includes(dateStr)){
        addedDaysArray.push(dateStr)
      }
      const daysToAdd = addedDaysArray.filter(d => d >= fromDateData && d <= dateStr)
      addedDays = daysToAdd.length

      // Calculate the difference between dayElem.dateObj and the last day of the last booking
      const lastBookingDate = new Date(lastBooking.to * 1000)
      // add 1 day to the from date to avoid conflicts with the end date of the previous booking
      lastBookingDate.setDate(lastBookingDate.getDate() + 2)
      const diffInDays = Math.ceil((dayElem.dateObj - lastBookingDate) / (1000 * 60 * 60 * 24))

      const timeDifference = Math.abs(date - lastBookingDate)
      const daysDifference = Math.round(timeDifference / (1000 * 60 * 60 * 24)) + 1 - addedDays

      // Check if the difference is equal to or greater than 7 and there is no booking on dayElem.dateObj
      if (date > lastBookingDate && daysDifference % 7 === 0 && !bookings.some(booking => new Date(booking.from * 1000).toDateString() === dayElem.dateObj.toDateString()) && !closedDates.includes(dateStr)) {
        // Add class to dayElem
        dayElem.className += " has-action"
      }
    },
    onChange: (selectedDates, dateStr, instance) => {
      fromDateData = dateStr
      const [year, month, day] = dateStr.split('-').map(Number)
      fromDate =  new Date(year, month - 1, day)
      toPickr.set('minDate', dateStr)
      // if from date is before a date in bookedDates then disable all dates after that date
      if(bookedDates){
        bookedDates.some(d => {
          const [year, month, day] = d.split('-').map(Number)
          const bookedDate = new Date(year, month - 1, day)
          if(fromDate < bookedDate){
            lastDay = bookedDate
            return true
          }
        })
      }
    }
  })

  // Trigger the onChange event to set the initial value of the toDateData variable
  datePick.config.onChange[0](datePick.selectedDates, datePick.input.value, datePick)
  toPickr.setDate(toDate)

  button.addEventListener("click", async (e) => {
    e.preventDefault() // Prevent the form submission
    e.stopPropagation()
    if (fromDateData > toDateData) {
        alert("Du kan ikke vælge en til dato som er før fra dato")
        return
    }
    if (fromDateData === undefined || toDateData === undefined || fromDateData === "" || toDateData === "") {
        alert("Du skal vælge en dato")
        return
    }
    if (fromDateData === undefined || toDateData === undefined) {
        alert("Du skal vælge en dato")
        return
    }

    // Check if the order has been invoiced
    if (customer.status === "1" || customer.status === 1) {
        if(confirm("Er du sikker på du vil ændre en faktureret booking?") === false) return
    }else{
        if(confirm("Er du sikker på du vil ændre denne booking?") === false) return
    }
    
    /* toDateData = await adjustBookingEndDateIfNeeded(fromDateData, toDateData) */
    
    // make sure there is no booking in the range of the selected dates 
    if (dates.length > 0) {
        let hasBookingConflict = false // Flag to track conflicts
        for (const [fromDate, toDate] of dates) {
          
        // add 1 day to the from date to avoid conflicts with the end date of the previous booking
        let newFromDate = new Date(fromDate)
        newFromDate.setDate(newFromDate.getDate())
        newFromDate = newFromDate.getFullYear() + "-" + ("0" + (newFromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + newFromDate.getDate()).slice(-2)

        // remove 1 day from the to date to avoid conflicts with the start date of the next booking
        let newToDate = new Date(toDate)
        newToDate.setDate(newToDate.getDate())
        newToDate = newToDate.getFullYear() + "-" + ("0" + (newToDate.getMonth() + 1)).slice(-2) + "-" + ("0" + newToDate.getDate()).slice(-2)

        if (
            (fromDateData <= newToDate && fromDateData >= newFromDate) ||
            (toDateData >= newFromDate && toDateData <= newToDate) ||
            (fromDateData <= newFromDate && toDateData >= newToDate)
        ) {
            console.log("picked dates: \n From: " + fromDateData + "\n To: " + toDateData)
            console.log("conflict dates: \n From: " + newFromDate + "\n To: " + newToDate)
            alert("Der er allerede en booking i det valgte tidsrum")
            hasBookingConflict = true // Set the flag to true
            break // Exit the loop when there's a conflict
        }
        }
    
        // If there's a booking conflict, prevent the form submission
        if (hasBookingConflict) {
          return
        }
    }
    const diffTime = Math.abs(new Date(toDateData) - new Date(fromDateData))
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
    const fromDate = new Date(fromDateData)/1000
    const toDate = new Date(toDateData)/1000
    const data = {
        booking_id: id,
        from: fromDate,
        to: toDate,
        fromDate: fromDateData,
        toDate: toDateData,
        days: diffDays,
        customer_id: customer.customer_id,
    }

    const res = await updateBooking(data)
    if(res.status == 1){
      const order = await updateOrder(data)
      if(order.status == false){
        alert("booking updateret men kunne ikke opdatere ordre")
      }else{
        alert(res.msg)
      }
    }else{
      alert(res.msg)
    }

    window.location.href = "/pos/rental/index.php?vare"
})
}

const editItem = async id => {
    const item = await getAllItemsFromId(id)
    const form = document.querySelector(".form")
    const input = []
    const checkboxes = []
    const allElements = []
    const deleteButtons = []
    // sort items by name 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
    item.sort((a, b) => parseInt(a.item_name) - parseInt(b.item_name))
    
    item.map(i => {
        input.push(createInputElement("text", "name", "form-control", i.item_name, i.id))
        /* const checkbox = createInputElement("checkbox", "reserved", "form-check-input", i.reserved, i.id) */
        const deleteButton = createButtonElement("button", "btn btn-danger delete", "Slet", i.id)
       /*  if(i.reserved == 1){
            checkbox.setAttribute("checked", "checked")
        } */
        deleteButtons.push(deleteButton)
        /* checkboxes.push(checkbox) */
    })

    input.forEach((i, index) => {
        const div = createLabelAndDivElement(`col-6 id${i.id}`, "Navn:", i, "form-label")
        /* const div2 = createLabelAndDivElement(`form-check col-2 pa-t-2 id${i.id}`, "Reserveret: ",  checkboxes[index], "form-check-label") */
        const div3 = createLabelAndDivElement(`col-1 ma-tb pt-2 id${i.id}`, "", deleteButtons[index], "")
        const hr = createHrElement(`col-9 id${i.id}`)
        allElements.push(div,/*  div2, */ div3, hr)
    })
    
    const button = createButtonElement("submit", "btn btn-primary", "Opdater alle stande")
    const createItemButton = createButtonElement("button", "btn btn-success ml-1", "Tilføj nye stande", "createItem")
    const div = createDivElement("col-12 ma-tb", [button, createItemButton])
    appendToFormGroup(form, [...allElements, div])

    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            const id = e.target.id
            const getBooking = await getItemBookings(id)
            
            if(getBooking.msg !== "Der er ingen bookinger"){
                // check if any of the bookings are on this day or in the future
                const bookings = []
                getBooking.forEach(b => {
                    const date = new Date(b.from * 1000)
                    const today = new Date()
                    if(date >= today){
                        bookings.push(b)
                    }
                })
                if(bookings.length > 0){
                    if(confirm("Der er bookinger på denne stand er du sikker på at du vil slette den?") === false) return
                }
                const response = await deleteItem(id)
            }else{
                if(settings.deletion == 1){
                    const response = await deleteItem(id)
                }else{
                    if(confirm("Er du sikker på at du vil slette denne stand?") === false) return
                    const response = await deleteItem(id)
                }
            }
            const getItems = document.querySelectorAll(`.id${e.target.id}`)
            getItems.forEach(i => {
                i.remove()
            })
        })
    })

    createItemButton.addEventListener("click", async (e) => {
        e.preventDefault()
        const count = parseInt(prompt("Hvor mange stande vil du tilføje?"))
        if(isNaN(count)) return
        const loading = document.querySelector("#loading")
        loading.style.display = "flex"
        
        const promise = Array.from(Array(parseInt(count)).keys()).map(async i => {
        const data = {
            item_name: "Ny stand",
            /* reserved: 0, */
            product_id: id
        }
        const res = await createItem(data)
        return res
        })

        const res = await Promise.all(promise)
        // reload page
        window.location.reload()
        // remove item from dom
        /* const getItems = document.querySelector(`.id${id}`)
        getItems.remove() */
    })

    button.addEventListener("click", async (e) => {
        e.preventDefault()
        // get all items
        const input = document.querySelectorAll("[name=name]")
        const inputArray = Array.from(input);
        const loading = document.querySelector("#loading")
        loading.style.display = "flex"
        const promise = inputArray.map(async (i) => {
            const data = {
                id: i.id,
                item_name: i.value,
                /* reserved: checkboxes[index].checked ? 1 : 0 */
            }
            const res = await updateItem(data)
            return res
        })
        const res = await Promise.all(promise)

        window.location.reload()

    })
    document.dispatchEvent(new CustomEvent('renderComplete'))
}
const queryString = window.location.search
if (queryString !== "") {
    const urlParams = new URLSearchParams(queryString)
    if(urlParams.has("id")) {
        editBooking(urlParams.get("id"))
    }else if(urlParams.has("item_id")){
        editItem(urlParams.get("item_id"))
    }
}
})()
