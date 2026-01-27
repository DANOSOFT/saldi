const productsView = async (main, db) => {
    const products = await fetch(`api.php?getAllProducts&db=${db}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })
    console.log(products)
    const div = document.createElement('div')
    div.classList.add("grid", "grid-cols-3", "gap-2")
    main.appendChild(div)
    products.forEach(product => {
    div.innerHTML += `<div id="${product.product_id}" class="flex flex-col items-center rounded-lg shadow md:flex-row md:max-w-xl border-gray-700 bg-gray-800 hover:bg-gray-700 products cursor-pointer">
                        <img class="object-cover w-full rounded-t-lg h-96 md:h-auto md:w-48 md:rounded-none md:rounded-s-lg" src="../documents/laja_55/varefotos/${product.product_id}" alt="">
                        <div class="flex flex-col justify-between p-4 leading-normal">
                            <h5 class="mb-2 text-2xl font-bold tracking-tight text-white">${product.product_name}</h5>
                            <p class="mb-3 font-normal text-gray-400">${(product.descript != null) ? product.descript : ""}</p>
                            <button id=${product.product_id} class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded w-20">Vælg</button>
                        </div>
                    </div>`
    })

    return {"products": products, "div": div}
}

const priceView = async (main, product) => {
    const div = document.createElement('div')
    main.appendChild(div)
    console.log(product)
    let unit
    if(product.unit.toLowerCase() === "dag"){
        unit = "dage"
    }else{
        unit = "uger"
    }
    const price = Math.round(product.price)*1.25
    div.innerHTML += `<div class="py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-6 lg:px-8"><button class="mt-10 w-24 block w-full rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 prev">Tilbage</button></div>
  <div class="mx-auto max-w-7xl px-6 lg:px-8">
    <div class="mx-auto mt-16 max-w-2xl rounded-3xl ring-1 ring-gray-200 sm:mt-20 lg:mx-0 lg:flex lg:max-w-none border-gray-700 bg-gray-800">
      <div class="p-8 sm:p-10 lg:flex-auto text-white">
        <h3 class="text-2xl font-bold tracking-tight">${product.product_name}</h3>
        <p class="mt-6 text-base leading-7">${(product.descript != null) ? product.descript : ""}</p>
        <div class="mt-10 flex items-center gap-x-4">
          <h4 class="flex-none text-sm font-semibold leading-6">Vælg ${unit}</h4>
          <div class="h-px flex-auto bg-gray-100"></div>
        </div>
        <div class="grid grid-cols-4 gap-2 mt-4 units">
        </div>
      </div>
      <div class="-mt-2 p-2 lg:mt-0 lg:w-full lg:max-w-md lg:flex-shrink-0">
        <div class="rounded-2xl bg-gray-50 py-10 text-center ring-1 ring-inset ring-gray-900/5 lg:flex lg:flex-col lg:justify-center lg:py-16">
          <div class="mx-auto max-w-xs px-8">
            <p class="text-base font-semibold text-gray-600 week-price">${product.periods[0].amount} ${(product.periods[0].amount == 1) ? unit.substring(0, 3) : unit}</p>
            <p class="mt-6 flex items-baseline justify-center gap-x-2">
              <span class="text-5xl font-bold tracking-tight text-gray-900 price">${price}</span>
              <span class="text-sm font-semibold leading-6 tracking-wide text-gray-600">DKK</span>
            </p>
            <button class="mt-10 block w-full rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 next">Vælg periode</button>
            <p class="mt-6 text-xs leading-5 text-gray-600">Du betaler først efter at have valgt hvilken periode du ønsker at booke standen</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>`
    const units = document.querySelectorAll('.units')
    product.periods.forEach(period => {
        if(period.amount == 1){
            units[0].innerHTML += `<button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded weeks" id="${period.amount}">${period.amount} ${unit.substring(0, 3)}</button>`
        }else{
            units[0].innerHTML += `<button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded weeks" id="${period.amount}">${period.amount} ${unit}</button>`
        }
    })
    const backButton = document.querySelector('.prev')
    backButton.addEventListener('click', (e) => {
        e.preventDefault()
        // refresh the site
        location.reload()
    })
    return {"div": div}
}

const calendarView = async (main, product, weeks, price, db) => {
  const dates = await fetch(`api.php?getAllDates=${product.product_id}&db=${db}`,{
    method: "GET",
    headers: {
        "Content-Type": "application/json"
    }
    }).then(response => {
        return response.json()
    })

    const div = document.createElement('div')
    main.appendChild(div)
    div.innerHTML += `<div class="py-24 sm:py-32">
    <div class="max-w-7xl mx-auto px-6 lg:px-8"><button class="mt-10 w-24 block w-full rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 prev">Tilbage</button></div>
  <div class="mx-auto max-w-7xl px-6 lg:px-8">
    <div class="mx-auto mt-16 max-w-2xl rounded-3xl ring-1 ring-gray-200 sm:mt-20 lg:mx-0 lg:flex lg:max-w-none border-gray-700 bg-gray-800">
        <div class="-mt-2 p-5">
            <div id="calendar"></div>
        </div>
        <div class="p-8 sm:p-10 lg:flex-auto text-white">
        <h3 class="text-2xl font-bold tracking-tight">${product.product_name}</h3>
        <p class="mt-6 text-base leading-7">${(product.descript != null) ? product.descript : ""}</p>
        <div class="grid grid-cols-2">
        <p class="mt-3 text-base leading-7 col-span-1">uger: </p>
        <p class="mt-3 text-base leading-7 col-span-1">${weeks}</p>
        <div class="flex items-center gap-x-4 col-span-2 mt-4">
            <div class="h-px flex-auto bg-gray-100"></div>
        </div>
        <p class="mt-3 text-base leading-7 col-span-1">Pris: </p>
        <p class="mt-3 text-base leading-7 col-span-1">${price} kr.</p>
        </div>
        <button data-modal-target="crud-modal" data-modal-toggle="crud-modal" class="mt-10 block w-full rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 w-24 float-end">lej stand</button>
        </div>
    </div>
  </div>
</div>`

    document.body.addEventListener('click', function(event) {

        if (event.target.matches('[data-modal-toggle]')) {
            const selectedDates = datePick.selectedDates
            if(selectedDates.length > 0){
                let modalId = event.target.getAttribute('data-modal-target')
                if(modalId == "" || modalId == null || modalId == undefined){
                    modalId = event.target.getAttribute('data-modal-toggle')
                }
                const modal = document.getElementById(modalId)
                if (modal) {
                    if (modal.classList.contains('hidden')) {
                        modal.classList.remove('hidden')
                    } else {
                        modal.classList.add('hidden')
                    }
                    modal.setAttribute('aria-hidden', 'false')
                } else {
                    console.log('No modal found with ID:', modalId)
                }
            }else{
                alert("Du skal vælge en dato")
            }
        }
    })

    const groupedDates = dates.reduce((acc, {id, rt_from, rt_to}) => {
    if (!acc[id]) {
        acc[id] = []
    }
    const rtFrom = new Date(rt_from * 1000)
    const rtTo = new Date(rt_to * 1000)
    acc[id].push({ rt_from: rtFrom, rt_to: rtTo})
    return acc
    }, {})

    // Object to store available dates by ID
    let availableDatesById = []

    // get closed dates
    const getClosedDates = await fetch(`api.php?getClosedDates&db=${db}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })

    const closedDates = getClosedDates.map(date => {
        date = new Date(date.date * 1000)
        date = date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
    })

    const datePick = flatpickr("#calendar", {
      inline: true, // This makes the calendar always visible
      mode: "range",
      dateFormat: 'Y-m-d',
      minDate: 'today',
      theme: "dark",
      locale: "da",
      disable: closedDates,
      onDayCreate: (dObj, dStr, fp, dayElem) => {
        const date = dayElem.dateObj
        const twoWeeksLater = new Date(date)
        twoWeeksLater.setDate(date.getDate() + (weeks*7)) // Corrected to always add weeks*7 days
    
        // Assume the date is unavailable initially
        let isDateAvailable = false
    
        // Check each item for availability
        Object.entries(groupedDates).forEach(([id, dateRanges]) => {
            let itemAvailable = true // Assume the current item is available unless a collision is found
            
            // Check current item's date ranges for availability
            dateRanges.forEach(({ rt_from, rt_to }) => {
    
                // If the date collides with the current range, mark the item as unavailable
                if ((date <= rt_to && twoWeeksLater >= rt_from)) {
                    itemAvailable = false;
                }
            })

            // If any item is available, mark the date as available
            if (itemAvailable) {
                isDateAvailable = true
                // get YYYY-MM-DD format of date
                const offset = date.getTimezoneOffset()
                const localDate = new Date(date.getTime() - offset * 60 * 1000)
                const newDate = localDate.toISOString().split('T')[0]
                availableDatesById.push([id, newDate])
            }
        })
    
        if (!isDateAvailable) {
            dayElem.classList.add("disabled")
        }
      },
      onChange: async (selectedDates, dateStr, instance) => {
        let startDate, endDate

        // Check if two dates are selected (start and end of the range)
        if (selectedDates.length === 2) {
            startDate = selectedDates[0]
            endDate = selectedDates[1]
        } else if (selectedDates.length === 1) {
            startDate = selectedDates[0]
            endDate = new Date(startDate)
            endDate.setDate(startDate.getDate() + weeks*7)

            // Update the calendar selection to the new range
            instance.setDate([startDate, endDate], true)
        }

        // Format startDate and endDate to YYYY-MM-DD
        startDate = startDate.toISOString().slice(0, 10)
        if (endDate) endDate = endDate.toISOString().slice(0, 10)
      }
  })

    const paymentButtons = document.querySelectorAll('.payment')
    paymentButtons.forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault()
            // close modal
            const modal = document.getElementById('crud-modal')
            if (modal) {
                modal.classList.add('hidden')
                modal.setAttribute('aria-hidden', 'true')
            } else {
                console.error('No modal found with ID:', modalId)
            }
            const selectedDates = datePick.selectedDates
            let startDate, endDate
            if (selectedDates.length === 2) {
                startDate = selectedDates[0]
                endDate = selectedDates[1]
            } else if (selectedDates.length === 1) {
                startDate = selectedDates[0]
                endDate = new Date(startDate)
                endDate.setDate(startDate.getDate() + weeks*7)
            }else{
                console.error("No dates selected")
                alert("Du skal vælge en dato")
                return
            }

            let custId
            
            if(e.target.id === "newUser"){
                const name = document.querySelector('input[name=name]').value
                const email = document.querySelector('input[name=email]').value
                const tlf = document.querySelector('input[name=tlf]').value
                const addr = document.querySelector('input[name=addr]').value
                const zip = document.querySelector('input[name=zip]').value
                const city = document.querySelector('input[name=city]').value
                if(name === "" || email === "" || tlf === "" || addr === "" || zip === "" || city === ""){
                    alert("Udfyld venligst alle felter")
                    return
                }

                let data = {
                    "name": name,
                    "email": email,
                    "tlf": tlf,
                    "addr": addr,
                    "zip": zip,
                    "city": city
                }

                const res = await fetch(`api.php?createCust&db=${db}`,{
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(data)
                }).then(response => {
                    return response.json()
                })

                custId = res.id
            }else{
                const accountNr = document.querySelector('input[name=accountNr]').value

                if(accountNr === ""){
                    alert("Udfyld venligst alle felter")
                    return
                }

                let data = {
                    "accountNr": accountNr
                }
                console.log(data)
                const res = await fetch(`api.php?createCust&db=${db}`,{
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(data)
                }).then(response => {
                    return response.json()
                })
                console.log(res.id)
                
                custId = res.id
            }

            let offset = startDate.getTimezoneOffset()
            let localDate = new Date(startDate.getTime() - offset * 60 * 1000)
            startDate = localDate.toISOString().split('T')[0]

            offset = endDate.getTimezoneOffset()
            localDate = new Date(endDate.getTime() - offset * 60 * 1000)
            endDate = localDate.toISOString().split('T')[0]

            // get id from availableDatesById based on startDate
            const id = availableDatesById.find(([id, date]) => date === startDate)[0]
            
            data = {
                "product_id": product.id,
                "start_date": startDate,
                "end_date": endDate,
                "weeks": weeks,
                "price": price * 0.8,
                "item_id": id,
                "cust_id": custId,
                "sku": product.sku
            }

            const response = await fetch(`api.php?createBooking&db=${db}`,{
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            }).then(response => {
                return response.json()
            })
            
            const bookingId = response.id
            const loading = document.querySelector("#loading")
            loading.style.display = "flex"
            const quickRes = await fetch("quickpay.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    "price": price,
                    "id": bookingId,
                    "db": db
                })
            }).then(response => {
                return response.json()
            })
            console.log(quickRes)
            // open payment window in a new windows
            window.open(quickRes.url, '', 'height=700,width=500')

            // get status every other second for 180 seconds
            let i = 0
            const interval = setInterval(async () => {
                i++
                const status = await fetch(`getStatus.php?id=${quickRes.id}`,{
                    method: "GET",
                    headers: {
                        "Content-Type": "application/json"
                    }
                }).then(response => {
                    return response.json()
                })
                console.log(status)
                if(status.state === "new"){
                    clearInterval(interval)
                    loading.style.display = "none"
                    // update booking status
                    const res = await fetch(`api.php?updateBooking&db=${db}`,{
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            "id": bookingId,
                            "status": "approved",
                            "custId": custId
                        })
                    }).then(response => {
                        return response.json()
                    })
                    main.innerHTML = `
                        <div class="w-2/3 mx-auto text-white text-center mt-4">
                            <h3>Tak for din bestilling!</h3>
                            <p>Vi har sendt en bekræftigelses mail med ordre.</p>
                        </div>
                    `
                }else if(status.state === "rejected"){
                    clearInterval(interval)
                    loading.style.display = "none"
                    alert("Betaling afvist")
                    // update booking status
                    const res = await fetch(`api.php?updateBooking&db=${db}`,{
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            "id": bookingId,
                            "status": "rejected"
                        })
                    }).then(response => {
                        return response.json()
                    })
                    location.reload()
                }else if(i === 95){
                    clearInterval(interval)
                    loading.style.display = "none"
                    alert("Timeout")
                    // update booking status
                    const res = await fetch(`api.php?updateBooking&db=${db}`,{
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            "id": bookingId,
                            "status": "timeout"
                        })
                    }).then(response => {
                        return response.json()
                    })
                    location.reload()
                }
            }, 2000)
        })
    })
        
    const backButton = document.querySelector('.prev')
    backButton.addEventListener('click', (e) => {
        e.preventDefault()
        // refresh the site
        location.reload()
    })
}

const main = async () => {
    // get url parameters
    const urlParams = new URLSearchParams(window.location.search)
    const db = urlParams.get('db')
    const main = document.querySelector('.main')
    const res = await productsView(main, db)
    const products = res.products
    const div = res.div
    const productsBtn = document.querySelectorAll('.products')
    productsBtn.forEach((item) => {
        item.addEventListener('click', async (e) => {
            e.preventDefault()
            const id = e.target.id
            const product = products.find(product => product.product_id == id)
            if (product) {
                div.remove()
                const res = await priceView(main, product)
                const weeks = document.querySelectorAll('.weeks')
                const weekPrice = document.querySelector('.week-price')
                const priceHTML = document.querySelector('.price')
                const next = document.querySelector('.next')
                let sum = product.price
                let weekAmount = 1
                next.addEventListener('click', (e) => {
                    res.div.remove()
                    calendarView(main, product, weekAmount, sum, db)

                })
                weeks.forEach(week => {
                    week.addEventListener('click', (e) => {
                        e.preventDefault()
                        const id = e.target.id
                        if(product.unit.toLowerCase() == "dag"){
                            weekPrice.innerHTML = `${id} dag${id > 1 ? 'e' : ''}`
                        }else{
                            weekPrice.innerHTML = `${id} uge${id > 1 ? 'r' : ''}`
                        }

                        let discountPeriods = []
                        let discountAmount = []
                        let i = -1
                        let discount = 0.00
                        let discountAmountValue = 0.00
                        let rabatart = ""
                        let price = 0
                        let paidWeeks = id
                        if (product.m_antal !== "" && product.m_rabat !== "" && product.m_antal !== "0" && product.m_rabat !== "0") {
                            if (product.m_antal.includes(";")) {
                                discountPeriods = product.m_antal.split(";")
                                discountAmount = product.m_rabat.split(";")
                            } else {
                                discountPeriods[0] = product.m_antal
                                discountAmount[0] = product.m_rabat
                            }

                            discountPeriods.forEach((period) => {
                                if(Number(period) <= Number(paidWeeks)){
                                    i++
                                }
                            })

                            if (i > -1) {
                                if (product.m_type === "percent") {
                                    discount = discountAmount[i]
                                    price = product.price * paidWeeks
                                    discountAmountValue = (product.price * discount) / 100
                                    rabatart = "percent"
                                } else {
                                    discount = discountAmount[i]
                                    price = product.price * paidWeeks
                                    discountAmountValue = discount * paidWeeks
                                    rabatart = "amount"
                                }
                            } else {
                                price = product.price * paidWeeks
                                discountAmountValue = 0.00
                                discount = 0.00
                                rabatart = ""
                            }
                        } else {
                            price = product.price * paidWeeks
                            discountAmountValue = 0.00
                            discount = 0.00
                            rabatart = ""
                        }

                        sum = (price - discountAmountValue) * 1.25
                        priceHTML.innerHTML = Math.round(sum)
                    })
                })
            } else {
                console.error(`No product found with id: ${id}`)
            }
        })
    })
}
main()