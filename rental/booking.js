import { getAllCustomers, getAllItems, getCustomers, createBooking } from "/pos/rental/api/api.js"

const cust = document.querySelector(".customers")

const createOptionElement = (value, text) => {
  const option = document.createElement("option")
  option.value = value
  option.text = text
  return option
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
      const id = i.item_id

      return [fromDateFormattad, toDateFormattad, id]
  })
}

const getCustomerTimes = async () => {
  const data = await getCustomers()
  if(data == "Der er ingen bookinger"){
      return []
  }
  return data.map(i => {
      const fromTime = new Date(i.from * 1000)
      const fromTimeFormatted = fromTime.getHours() + ":" + fromTime.getMinutes()
      const toTime = new Date(i.to * 1000)
      const toTimeFormatted = toTime.getHours() + ":" + toTime.getMinutes()
      return [fromTimeFormatted, toTimeFormatted]
  })
}

const drawForm = async () => {
  const changingInput = document.querySelector(".changing-input")
  while(changingInput.firstChild){
    changingInput.removeChild(changingInput.firstChild)
  }

  const itemsSelect = document.querySelector(".items")
  while(itemsSelect.firstChild){
    itemsSelect.removeChild(itemsSelect.firstChild)
  }
  const items = await getAllItems()
  
  const format = document.querySelector(".format")
  const formatData = format.value

  const createInputElement = (type, name, className) => {
    const input = document.createElement("input")
    input.setAttribute("type", type)
    input.setAttribute("name", name)
    input.setAttribute("class", className)
    return input
  }

  const createLabelAndDivElement = (className, labelText, element) => {
    const div = document.createElement("div")
    const label = document.createElement("label")
    label.textContent = labelText
    div.setAttribute("class", className)
    div.appendChild(label)
    div.appendChild(element)
    return div
  }

  const appendToFormGroup = (rootElement, elements) => {
    elements.forEach(element => {
      rootElement.appendChild(element)
    })
  }

  const addEventListeners = (elements, event, handler) => {
    elements.forEach(element => {
      element.addEventListener(event, handler)
    })
  }

  if (formatData === "1") {
    const rootElement = document.querySelector(".changing-input")
    const fromDate = createInputElement("date", "fromDate", "form-control")
    const fromDateDiv = createLabelAndDivElement("form-group", "dato", fromDate)
    const fromTime = createInputElement("time", "fromTime", "form-control")
    const fromTimeDiv = createLabelAndDivElement("form-group col-6", "Fra", fromTime)
    const toTime = createInputElement("time", "toTime", "form-control")
    const toTimeDiv = createLabelAndDivElement("form-group col-6", "Til", toTime)
    appendToFormGroup(rootElement, [fromDateDiv, fromTimeDiv, toTimeDiv])

    let fromDateData, fromTimeData, toTimeData

    const handleChange = () => {
      fromDateData = fromDate.value
      fromTimeData = fromTime.value
      toTimeData = toTime.value
      getItems()
    }

    addEventListeners([fromDate, fromTime, toTime], "change", handleChange)

    const getItems = async () => {
      if (fromDateData && fromTimeData && toTimeData) {
        if(fromTimeData > toTimeData){
          alert("Du kan ikke velge en til tid som er før fra tid")
          return
        }
        const itemsUnavailable = []

        const dates = await getCustomerDates()
        const times = await getCustomerTimes()
        const [year, month, day] = fromDateData.split("-")
        const [fromHours, fromMinutes] = fromTimeData.split(":")
        const [toHours, toMinutes] = toTimeData.split(":")
        const resevationDate = year + "-" + month + "-" + day
        const fromTime = fromHours + ":" + fromMinutes
        const toTime = toHours + ":" + toMinutes
        dates.forEach(date => {
          if(resevationDate == date[0]){
            times.forEach(time => {
              if((fromTime >= time[0] && fromTime <= time[1]) || (toTime >= time[0] && toTime <= time[1])){
                itemsUnavailable.push(date[2])
              }
            })
          }
        })

        items.forEach(i => {
          if(!itemsUnavailable.includes(i.id)){
            const option = createOptionElement(i.id, i.item_name)
            itemsSelect.appendChild(option)
          }
        })
        document.querySelector(".form").addEventListener("submit", async e => {
          e.preventDefault()
          if(itemsSelect.value === undefined){
            alert("Du skal vælge en vare")
            return
          }
          const data = {
            customer_id: cust.value,
            item_id: itemsSelect.value,
            from: new Date(fromDateData + "T" + fromTimeData)/1000,
            to: new Date(fromDateData + "T" + toTimeData)/1000
          }
          const res = await createBooking(data)
          alert(res)
          window.location.href = "/laja/rental/index.php"
        })
      }
    }
  }else if(formatData === "2"){
    const rootElement = document.querySelector(".changing-input")
    const fromDate = createInputElement("date", "fromDate", "form-control")
    const fromDateDiv = createLabelAndDivElement("form-group col-6", "Fra", fromDate)
    const toDate = createInputElement("date", "toDate", "form-control")
    const toDateDiv = createLabelAndDivElement("form-group col-6", "Til", toDate)
    appendToFormGroup(rootElement, [fromDateDiv, toDateDiv])

    let fromDateData, toDateData

    const handleChange = () => {
      fromDateData = fromDate.value
      toDateData = toDate.value
      getItems()
    }

    addEventListeners([fromDate, toDate], "change", handleChange)

    const getItems = async () => {
      if(fromDateData && toDateData) {
        if(fromDateData > toDateData){
          alert("Du kan ikke velge en til dato som er før fra dato")
          return
        }
        const itemsUnavailable = []

        const dates = await getCustomerDates()
        console.log(dates)
        const [fromYear, fromMonth, fromDay] = fromDateData.split("-")
        const [toYear, toMonth, toDay] = toDateData.split("-")
        const fromDate = fromYear + "-" + fromMonth + "-" + fromDay
        const toDate = toYear + "-" + toMonth + "-" + toDay
        dates.forEach(date => {
          if(fromDate >= date[0] && fromDate <= date[1] || (toDate >= date[0] && toDate <= date[1])){
            itemsUnavailable.push(date[2])
          }
        })

        items.forEach(i => {
          if(!itemsUnavailable.includes(i.id.toString())){
            const option = createOptionElement(i.id, i.item_name, i.item_size)
            itemsSelect.appendChild(option)
          }
        })
        document.querySelector(".form").addEventListener("submit", async e => {
          e.preventDefault()
          if(itemsSelect.value === undefined){
            alert("Du skal vælge en vare")
            return
          }
          const fromDate = new Date(fromDateData)/1000
          const toDate = new Date(toDateData)/1000
          console.log(fromDate + " " + toDate)
          const data = {
            customer_id: cust.value,
            item_id: itemsSelect.value,
            from: fromDate,
            to: toDate
          }
          const res = await createBooking(data)
          alert(res)
          window.location.href = "/laja/rental/index.php"
        })
      }
    }
  }
  format.addEventListener("change", drawForm)
}

const init = async () => {
  const customers = await getAllCustomers()
  const cust = document.querySelector(".customers")
  customers.forEach(customer => {
    const option = createOptionElement(customer.id, customer.name)
    cust.appendChild(option)
  })
  drawForm()
}

init()

