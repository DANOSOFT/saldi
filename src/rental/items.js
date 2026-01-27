// Constants
const EDIT_ICON = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
<path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
<path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
</svg>`

// DOM Element Creators
const createElement = (tag, content) => {
    const element = document.createElement(tag)
    element.innerHTML = content
    return element
}

const createTdElement = text => createElement("td", text)
const createThElement = text => createElement("th", text)

// Authentication
const checkPassword = async (settings) => {
    if (settings.use_password !== "1") return true
    
    const password = prompt("Indtast adgangskode for at fortsætte")
    if (password !== settings.pass) {
        alert("Forkert adgangskode")
        const currentUrl = new URL(window.location.href)
        const currentPathSegments = currentUrl.pathname.split('/').filter(segment => segment !== '')
        const redirectFolder = currentPathSegments[0]
        window.location.href = `/${redirectFolder}/rental/index.php?vare`
        return false
    }
    return true
}

// Data Processing
const getProductCounts = (items) => {
    const productIds = [...new Set(items.map(item => item.product_id))]
    const count = {}
    productIds.forEach(id => count[id] = 0)
    items.forEach(item => count[item.product_id]++)
    return count
}

// Table Creation
const createTableHeader = () => {
    const tr = document.createElement("tr")
    tr.appendChild(createThElement("Navn"))
    tr.appendChild(createThElement("Antal"))
    tr.appendChild(createThElement("Rediger"))
    return tr
}

const createTableRow = (item, count) => {
    const tr = document.createElement("tr")
    tr.appendChild(createTdElement(item.product_name))
    tr.appendChild(createTdElement(`<button class="btn btn-success number" id="${item.product_id}" disabled>${count[item.product_id]}</button>`))
    tr.appendChild(createTdElement(`<a href="edit.php?item_id=${item.product_id}" class="btn btn-primary edit">${EDIT_ICON}</a>`))
    return tr
}

const createTable = async () => {
    const { getAllItems, getSettings } = await import(`/${window.location.pathname.split('/')[1]}/rental/api/api.js`)
    const settings = await getSettings()
    
    if (!await checkPassword(settings)) return
    
    const items = await getAllItems()
    if (items === "Der er ingen stande") return
    
    const tBody = document.querySelector("tBody")
    tBody.appendChild(createTableHeader())
    
    const productCounts = getProductCounts(items)
    const processedProducts = new Set()
    
    items.forEach(item => {
        if (!processedProducts.has(item.product_id)) {
            tBody.appendChild(createTableRow(item, productCounts))
            processedProducts.add(item.product_id)
        }
    })
    
    document.dispatchEvent(new CustomEvent('renderComplete'))
}

// Initialize
createTable()