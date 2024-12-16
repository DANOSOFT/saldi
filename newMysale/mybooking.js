const init = async () => {
    const urlParams = new URLSearchParams(window.location.search)
    const userLink = urlParams.get('id')

    const fetchDB = await fetch(`getDB.php?id=${userLink}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })

    const db = fetchDB.db
    const userID = fetchDB.id

    const bookings = await fetch(`api.php?getAllBookingsByUser=${userID}&db=${db}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })

    const table = document.querySelector(".tablePoint")
    const thead = `<thead class="text-gray-700 uppercase bg-gray-50 dark:bg-gray-500 dark:text-gray-300">
        <tr>
            <th scope="col" class="px-6 py-3">Stand</th>
            <th scope="col" class="px-6 py-3">Fra</th>
            <th scope="col" class="px-6 py-3">Til</th>
        </tr></thead><tbody class="tbody"></tbody>`
    table.innerHTML = thead

    const tbody = document.querySelector(".tbody")
    // sort booking by from date
    bookings.sort((a, b) => {
        return b.rt_from - a.rt_from
    })
    bookings.forEach(booking => {
        console.log(booking)
        const tr = `<tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            <td class="px-6 py-4">${booking.item_name}</td>
            <td class="px-6 py-4">${new Date(booking.rt_from * 1000).getFullYear() + "-" + ("0" + (new Date(booking.rt_from * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.rt_from * 1000).getDate()).slice(-2)}</td>
            <td class="px-6 py-4">${new Date(booking.rt_to * 1000).getFullYear() + "-" + ("0" + (new Date(booking.rt_to * 1000).getMonth() + 1)).slice(-2) + "-" + ("0" + new Date(booking.rt_to * 1000).getDate()).slice(-2)}</td>
        </tr>`
        tbody.innerHTML += tr
    })
}
init()