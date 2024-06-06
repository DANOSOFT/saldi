import { getAllCustomers, getAllItems, getCustomers, createBooking, getSettings} from "/pos/rental/api/api.js"

const settings = await getSettings()

const createOptionElement = (value, text) => {
    const option = document.createElement("option")
    option.value = value
    option.text = text
    return option
}

const customers = await getAllCustomers()
const cust = document.querySelector(".customers")
cust.innerHTML = ""
customers.forEach(customer => {
  const option = createOptionElement(customer.id, customer.name)
  cust.appendChild(option)
})

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
        const id = i.item_id
        return {
            from: fromDateFormattad,
            to: toDateFormattad,
            item_id: id
        }
    })
}

const items = await getAllItems()
const itemsSelect = document.querySelector(".items")
items.forEach(item => {
    itemsSelect.appendChild(createOptionElement(item.id, item.item_name))
})

const setDates = () => {
    const date = fromDate.valueAsDate
    fromDateData = date / 1000
    toDateData = date.setDate(date.getDate() + 7 * weeks.value) / 1000
}

const setUnavailableItems = async (from, to) => {
    const fromDate = new Date(from * 1000)
    const fromDateFormattad = fromDate.getFullYear() + "-" + ("0" + (fromDate.getMonth() + 1)).slice(-2) + "-" + ("0" + fromDate.getDate()).slice(-2)
    const toDate = new Date(to * 1000)
    const toDateFormattad = toDate.getFullYear() + "-" + ("0" + (toDate.getMonth() + 1)).slice(-2) + "-" + ("0" + toDate.getDate()).slice(-2)
    const dates = await getCustomerDates()
    const unavailableItems = []
    dates.forEach(date => {
        if ((fromDateFormattad <= date.to && fromDateFormattad >= date.from) ||
        (toDateFormattad >= date.from && toDateFormattad <= date.to) ||
        (fromDateFormattad <= date.from && toDateFormattad >= date.to)) {
            unavailableItems.push(date.item_id)
        }
    })
    return unavailableItems
}


let fromDateData, toDateData

const fromDate = document.querySelector(".from")

flatpickr(fromDate, {
    dateFormat: 'Y-m-d',
    minDate: 'today',
    disable: allDatesWithoutEnds,
    onChange: (selectedDates, dateStr, instance) => {
      fromDateData = dateStr
    }
})

fromDate.addEventListener("change", async () => {
    const weeks = document.querySelector(".weeks")
    if(weeks.value === undefined || weeks.value === null) return
    setDates()
    if(fromDateData === undefined || fromDateData === null || toDateData == undefined || toDateData == null) return
    const unavailableItems = await setUnavailableItems(fromDateData, toDateData)
    itemsSelect.innerHTML = ""
    items.forEach(item => {
        if(unavailableItems.includes(item.id)) return
        itemsSelect.appendChild(createOptionElement(item.id, item.item_name))
    })
})

const weeks = document.querySelector(".weeks")
weeks.addEventListener("change", async () => {
    if(fromDateData === undefined || fromDateData === null) return
    setDates()
    if(fromDateData === undefined || fromDateData === null || toDateData == undefined || toDateData == null) return
    const unavailableItems = await setUnavailableItems(fromDateData, toDateData)
    console.log(unavailableItems)
    itemsSelect.innerHTML = ""
    items.forEach(item => {
        if(unavailableItems.includes(item.id)) return
        itemsSelect.appendChild(createOptionElement(item.id, item.item_name))
    })
})


const form = document.querySelector(".form")
form.addEventListener("submit", async (e) => {
    e.preventDefault()
    if (form.item.value === undefined) {
        alert("Du skal vælge en vare")
        return
    }
    if (form.customer.value === undefined) {
        alert("Du skal vælge en kunde")
        return
    }
    if (form.from.value === undefined) {
        alert("Du skal vælge en start dato")
        return
    }
    if (form.weeks.value === undefined) {
        alert("Du skal vælge antal uger")
        return
    }


    const data = {
        customer_id: form.customer.value,
        item_id: form.item.value,
        from: fromDateData,
        to: toDateData
    }


    const loading = document.querySelector("#loading")
    loading.style.display = "flex"
    const res = await createBooking(data)
    data.booking_id = res.id

    const response = await createOrder(data)
    loading.style.display = "none"
    alert(res.msg)
    window.location.href = "/pos/rental/index.php?vare"
})
