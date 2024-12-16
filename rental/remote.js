// Make sure you're in an async context
(async () => {
    const url = new URL(window.location.href)
    const pathSegments = url.pathname.split('/').filter(segment => segment !== '')
    const firstFolder = pathSegments[0]
    // Dynamically import the module
    const { getAllProducts, updateRemoteProduct, updateMail, getMailInfo, getSettings, getPayment, updatePayment, getRemoteLink } = await import(`/${firstFolder}/rental/api/api.js`)

const editIcon = `<svg xmlns="http://www.w3.org/2000/svg" style="pointer-events: none;" width="24" height="24" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
<path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
<path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
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
const content = document.querySelector(".content")
const products = await getAllProducts()
const remoteLink = await getRemoteLink()
// create initial function
const init = async () => {
    // get mail info
    const mailInfo = await getMailInfo()
    const payment = await getPayment()

    content.innerHTML = `
        <table class="table table-responsive-sm table-light table-striped w-75 mx-auto">
            <thead>
                <tr>
                    <th scope="col">Varenr</th>
                    <th scope="col">Navn</th>
                    <th scope="col">Redigere</th>
                </tr>
            </thead>
            <tbody class="tBody">
            </tbody>
        </table>
        <form method="post" class="w-75 mx-auto mt-4">
            <div class="mb-3">    
                <h3>Mailopsætning</h3>
                <p>Hvis i gerne vil ha vi sender ordrebekræftelse fra jeres mail.</p>
            </div>
            <div class="mb-3">
                <label for="host" class="form-label">host</label>
                <input type="text" class="form-control" id="host" value="${(mailInfo.host != null) ? mailInfo.host : ""}">
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Brugernavn</label>
                <input type="text" class="form-control" id="username" value="${(mailInfo.username != null) ? mailInfo.username : ""}">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">password</label>
                <input type="password" class="form-control" id="password" value="${(mailInfo.password != null) ? mailInfo.password : ""}">
            </div>
            <button type="submit" class="btn btn-primary saveMail">Gem</button>
        </form>
        <hr class="w-75 mx-auto mt-5 mb-5">
        <form method="post" class="w-75 mx-auto mt-4">
            <div class="mb-3">
                <h3>Vibrant</h3>
                <p>Hvis i gerne vil bruge ekstern booking, skal i indsætte jeres vibrant oplysninger</p>
            </div>
            <div class="mb-3">
                <label for="apiKey" class="form-label">API Key</label>
                <input type="text" class="form-control" id="apiKey" value="${(payment.apikey != null) ? payment.apikey : ""}">
            </div>
            <div class="mb-3">
                <label for="tradeConditions" class="form-label">Link til fil med jeres/dine handelsbetingelser</label>
                <input type="text" class="form-control" id="tradeConditions" value="${(payment.tradeConditions != null) ? payment.tradeConditions : ""}">
            </div>
            <button type="submit" class="btn btn-primary savePayment">Gem</button>
        </form>
        <hr class="w-75 mx-auto mt-5 mb-5">
        <div class="mt-4 w-75 mx-auto">
            <p>Link til jeres eksterne booking: ${remoteLink}</p>
        </div>
    `

    // add event listener to save mail button
    document.querySelector(".saveMail").addEventListener("click", async (e) => {
        e.preventDefault()
        const host = document.querySelector("#host").value
        const username = document.querySelector("#username").value
        const password = document.querySelector("#password").value
        const mail = {
            host,
            username,
            password
        }
        const res = await updateMail(mail)
        alert(res)
    })

    // add event listener to save payment button
    document.querySelector(".savePayment").addEventListener("click", async (e) => {
        e.preventDefault()
        const apiKey = document.querySelector("#apiKey").value
        const tradeConditions = document.querySelector("#tradeConditions").value
        const payment = {
            apiKey,
            tradeConditions
        }
        const res = await updatePayment(payment)
        alert(res)
    })

    // sort products by name
    products.sort((a, b) => a.product_name.localeCompare(b.product_name))

    // populate table with products
    products.forEach(p => {
        const tbody = document.querySelector(".tBody")
        const tr = document.createElement("tr")
        const td1 = document.createElement("td")
        const td2 = document.createElement("td")
        const td3 = document.createElement("td")
        td1.textContent = p.id
        td2.textContent = p.product_name
        td3.innerHTML = `<button class="btn btn-primary edit" id="${p.id}">${editIcon}</button>`
        tr.appendChild(td1)
        tr.appendChild(td2)
        tr.appendChild(td3)
        tbody.appendChild(tr)
    })
    // add event listener to edit buttons
    const editButtons = document.querySelectorAll(".edit")
    editButtons.forEach(b => {
    b.addEventListener("click", e => {
        const id = e.target.id
        // find product by id
        const product = products.find(p => p.id === id)
        // set units
        if(product.unit.toLowerCase() === "dag"){
            product.units = "Dage"
        }else{
            product.units = "Uger"
        }
        // Create form for editing product
        content.innerHTML = `
        <h3 class="text-center">${product.product_name}</h3>
        <hr class="w-50 mx-auto mt-5 mb-5">
        <form class="w-50 mx-auto">
            <div class="mb-3">
                <label for="product_desc" class="form-label">Beskrivelse</label>
                <input type="text" class="form-control" id="product_desc" value="${(product.descript != null) ? product.descript : ""}">
            </div>
            <div class="form-check form-switch mb-3">
                <input type="checkbox" role="switch" id="activeCheckBox" class="form-check-input" ${(product.is_active != "0") ? "checked" : ""}>
                <label for="activeCheckBox" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300 activeLabel">${(product.is_active != "0") ? "Aktive" : "Inaktiv"}</label>
            </div>
            <div class="mb-3">
                <input id="choose" type="checkbox" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" ${(product.choose_periods != "0") ? "checked" : ""}>
                <label for="choose" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Vælg selv uger (giver brugeren mulighed for selv at vælge antal uger)</label>
            </div>
            <div class="mb-3">
                <label for="max" class="form-label">Max uger i vælg selv</label>
                <input type="number" class="form-control" id="max" value="${(product.max != null) ? product.max : ""}">
            </div>
            <div class="mb-3 periods">

            </div>
            <button type="button" class="btn btn-secondary add-period">Add Period</button>
            <button type="button" class="btn btn-secondary remove-period">Remove Period</button>
            <button type="submit" class="btn btn-primary save">Gem</button>
        </form>
        `
        const periods = document.querySelector(".periods")

        // add event listener to active checkbox
        const activeLabel = document.querySelector(".activeLabel")
        const activeCheckBox = document.querySelector("#activeCheckBox")
        activeCheckBox.addEventListener("change", () => {
            activeLabel.textContent = activeCheckBox.checked ? "Aktive" : "Inaktiv"
        })

        let i = 1
        // add periods to form
        product.periods.forEach(p => {
            addPeriodInput(periods, i, p.amount, product)
            i++
        })
        if (i === 1) {
            addPeriodInput(periods, i, "", product)
        }
        // add event listeners to add and remove period buttons
        document.querySelector(".add-period").addEventListener("click", () => {
            if (periods.children.length < 4) {
                addPeriodInput(periods, periods.children.length + 1, "", product)
            }
        })

        document.querySelector(".remove-period").addEventListener("click", () => {
            if (periods.children.length > 1) {
                periods.removeChild(periods.lastChild)
            }
        })
        // add event listener to save button
        const saveButton = document.querySelector(".save")
        saveButton.addEventListener("click", async (e) => {
            e.preventDefault()
            // get values from form
            const productDesc = document.querySelector("#product_desc").value
            const max = document.querySelector("#max").value
            const periods = document.querySelectorAll("#period")
            const isActive = document.querySelector("#activeCheckBox").checked ? 1 : 0
            const choosePeriods = document.querySelector("#choose").checked ? 1 : 0
            const product = {
                id: id,
                product_desc: productDesc,
                periods: [],
                is_active: isActive,
                max,
                choose_periods: choosePeriods
            }
            console.log(product)
            // add periods to product
            periods.forEach(p => {
                product.periods.push({amount: p.value})
            })
            // Send product to api
            const res = await updateRemoteProduct(product)
            alert(res)
            // reload page
            location.reload()
        })
    })
})
}

init()

// Helper function to add period input fields
function addPeriodInput(container, index, value, product) {
    const div = document.createElement("div")
    div.classList.add("mb-3")
    div.innerHTML = `
    <label for="period" class="form-label">Periode ${index}</label>
    <div class="row">
        <div class="col-10">
            <input type="text" class="form-control" id="period" value="${value}">
        </div>
        <div class="col">
            <span>${product.unit} / ${product.units}</span>
        </div>
    </div>
    `
    container.appendChild(div)
}
})()