import { getBooking, updateBooking, getItem, updateItem} from "/pos/rental/api/api.js"

const createInputElement = (type, name, className, value) => {
    const input = document.createElement("input")
    input.setAttribute("type", type)
    input.setAttribute("name", name)
    input.setAttribute("class", className)
    input.setAttribute("value", value)
    return input
}

const createButtonElement = (type, className, value) => {
    const button = document.createElement("button")
    button.setAttribute("type", type)
    button.setAttribute("class", className)
    button.textContent = value
    return button
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

const editBooking = async (id) => {
    const customer = await getBooking(id)
    const from = new Date(customer.from * 1000)
    const to = new Date(customer.to * 1000)
    const fromDate = from.getFullYear() + "-" + ("0" + (from.getMonth() + 1)).slice(-2) + "-" + ("0" + from.getDate()).slice(-2)
    const toDate = to.getFullYear() + "-" + ("0" + (to.getMonth() + 1)).slice(-2) + "-" + ("0" + to.getDate()).slice(-2)
    const fromTime = from.getHours() + ":" + from.getMinutes()
    const toTime = to.getHours() + ":" + to.getMinutes()
    const form = document.querySelector(".form")
    const row = document.querySelector(".rental-option")
    const name = document.querySelector(".name")
    name.textContent = customer.name
    if(fromDate != toDate){
        const inputFrom = createInputElement("date", "fromDate", "form-control", fromDate)
        const div = createLabelAndDivElement("form-group col-6", "Fra", inputFrom)
        const inputTo = createInputElement("date", "toDate", "form-control", toDate)
        const div2 = createLabelAndDivElement("form-group col-6", "Til", inputTo)
        const button = createButtonElement("submit", "btn btn-primary", "Opdater")
        appendToFormGroup(row, [div, div2])
        appendToFormGroup(form, [button])

        button.addEventListener("click", async (e) => {
            e.preventDefault()
            const data = {
                id: id,
                from: new Date(document.querySelector("[name=fromDate]").value).getTime() / 1000,
                to: new Date(document.querySelector("[name=toDate]").value).getTime() / 1000
            }
            const response = await updateBooking(data)
            alert(response)
            window.location.href = "index.php"
        })
    }else{
        const inputFrom = createInputElement("date", "fromDate", "form-control", fromDate)
        const div = createLabelAndDivElement("form-group", "Dato", inputFrom)
        const inputFromTime = createInputElement("time", "fromTime", "form-control", fromTime)
        const div2 = createLabelAndDivElement("form-group col-6", "Fra", inputFromTime)
        const inputToTime = createInputElement("time", "toTime", "form-control", toTime)
        const div3 = createLabelAndDivElement("form-group col-6", "Til", inputToTime)
        const button = createButtonElement("submit", "btn btn-primary", "Opdater")
        appendToFormGroup(form, [div, button])
        appendToFormGroup(row, [div2, div3])

        button.addEventListener("click", async (e) => {
            e.preventDefault()
            const data = {
                id: id,
                from: new Date(document.querySelector("[name=fromDate]").value + "T" + document.querySelector("[name=fromTime]").value).getTime() / 1000,
                to: new Date(document.querySelector("[name=fromDate]").value + "T" + document.querySelector("[name=toTime]").value).getTime() / 1000
            }
            const response = await updateBooking(data)
            alert(response)
            window.location.href = "index.php"
        })
    }
}

const editItem = async id => {
    const item = await getItem(id)
    const form = document.querySelector(".form")
    const input = createInputElement("text", "name", "form-control", item.item_name)
    const div = createLabelAndDivElement("form-group", "Navn", input)
    const button = createButtonElement("submit", "btn btn-primary", "Opdater")
    appendToFormGroup(form, [div, button])
    
    button.addEventListener("click", async (e) => {
        e.preventDefault()
        const data = {
            id: id,
            item_name: document.querySelector("[name=name]").value
        }
        const res = await updateItem(data)
        alert(res)
        window.location.href = "items.php"
    })
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