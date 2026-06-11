// Make sure you're in an async context
(async () => {
    const url = new URL(window.location.href)
    const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
    const firstFolder = pathSegments[0]
    // Dynamically import the module
    const { getClosedDays, insertClosedDay, deleteClosedDay, getSettings } = await import(`/${firstFolder}/rental/api/api.js`)

const deleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" style="pointer-events: none;" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
</svg>`

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

const createTdElement = text => {
    const td = document.createElement("td")
    td.innerHTML = text
    return td
}

const createThElement = text => {
    const th = document.createElement("th")
    th.innerHTML = text
    return th
}

const closedDays = await getClosedDays()

const getDates = () => {
    const dates = []
    if(closedDays.success == false) return closedDays.msg
    closedDays.forEach(i => {
        const date = new Date(i.date * 1000)
        const year = date.getFullYear()
        const month = (date.getMonth() + 1 < 10) ? "0" + (date.getMonth() + 1) : date.getMonth() + 1
        const day = (date.getDate() < 10) ? "0" + date.getDate() : date.getDate()
        const formattedDate = year + "-" + month + "-" + day
        dates.push({date: formattedDate, id: i.id})
    })
    return dates
}

const createTable = async () => {
    const tBody = document.querySelector(".tBody")
    tBody.innerHTML = ""
    const closedDays = getDates()
    if(closedDays == "Der er ingen lukkede dage"){
        const tr = document.createElement("tr")
        const td = createTdElement("Ingen lukkedage")
        td.setAttribute("colspan", "2")
        tr.appendChild(td)
        tBody.appendChild(tr)
        return
    }
    // set headers
    const tr = document.createElement("tr")
    const date = createThElement("Dato")
    const del = createThElement("Slet")
    tr.appendChild(date)
    tr.appendChild(del)
    tBody.appendChild(tr)
    // order dates by date newest first
    const dates = getDates().sort((a, b) => {
        return new Date(b.date) - new Date(a.date)
    })
    dates.forEach(i => {
        const tr = document.createElement("tr")
        const date = createTdElement(i.date)
        const del = createTdElement("<button class='btn btn-danger delete' id='" + i.id + "'>" + deleteIcon + "</button>")
        tr.appendChild(date)
        tr.appendChild(del)
        tBody.appendChild(tr)
    })

    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(b => {
        b.addEventListener("click", async e => {
            e.preventDefault()
            const id = b.id
            const response = await deleteClosedDay(id)
            alert(response)
            window.location.reload()
        })
    })
}

createTable()

const button = document.querySelector(".btn-primary")
let dates = getDates()
    if(dates != "Der er ingen lukkede dage"){
        dates = dates.map(i => i.date)
    }else{
        dates = []
    }
const datePick = flatpickr("#calendar", {
    inline: true, // This makes the calendar always visible
    dateFormat: 'Y-m-d',
    minDate: 'today',
    theme: "dark",
    locale: "da",
    onDayCreate: (dObj, dStr, fp, dayElem) => {
        // add class to disable dates
        const date = dayElem.dateObj.getFullYear() + "-" + 
             String(dayElem.dateObj.getMonth() + 1).padStart(2, '0') + "-" + 
             String(dayElem.dateObj.getDate()).padStart(2, '0')
            if(dates.includes(date)){
                dayElem.classList.add("disabled");
            }
    },
    disable: dates,
    onChange: async (selectedDates, dateStr, instance) => {
        // ask if they cornfirm the date
        const confirm = window.confirm("Er du sikker på du vil lukke for udlejning på denne dag?")
        if(!confirm) return
        // insert the date
        const date = new Date(dateStr)
        const unix = date.getTime() / 1000
        const data = {
            day: unix,
        }
        const response = await insertClosedDay(data)
        alert(response)
        window.location.reload()
    }
})
document.dispatchEvent(new CustomEvent('renderComplete'))
})()