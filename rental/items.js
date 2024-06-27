import {getAllItems, deleteItem} from "/pos/rental/api/api.js"

const editIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
<path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
<path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
</svg>`

const deleteIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
<path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
</svg>`


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
const createTable = async () => {

    const items = await getAllItems()
    const tBody = document.querySelector("tBody")
    // set headers
    const tr = document.createElement("tr")
    const name = createThElement("Navn")
    const edit = createThElement("Redigere")
    const del = createThElement("Slet")
    tr.appendChild(name)
    tr.appendChild(edit)
    tr.appendChild(del)
    tBody.appendChild(tr)

    items.forEach(i => {
        const tr = document.createElement("tr")
        const name = createTdElement(i.item_name)
        const edit = createTdElement("<a href='edit.php?item_id=" + i.id + "' class='btn btn-primary'>" + editIcon + "</a>")
        const del = createTdElement("<button class='btn btn-danger delete' id='" + i.id + "'>" + deleteIcon + "</button>")
        tr.appendChild(name)
        tr.appendChild(edit)
        tr.appendChild(del)
        tBody.appendChild(tr)
    })

    const deleteButtons = document.querySelectorAll(".delete")
    deleteButtons.forEach(button => {
        button.addEventListener("click", async (e) => {
            console.log(e.target.id)
            const id = e.target.id
            const res = await deleteItem(id)
            alert(res)
            window.location.reload()
        })
    })
}

createTable()