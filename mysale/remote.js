// 20261006 CL/LH SST-842: stop with a message when vibrantPaymentLink.php returns an error instead of a link.
// 20261015 CL/LH SST-842: reject the booking (deletes the order and rentalperiod) when no payment link is returned.
// 20261105 CL/LH createBooking's refusal (409: the period is no longer free, 400: no booking) is shown, and no payment link is asked for.
// 20261105 CL/LH After the payment the page says the confirmation was mailed only when sendMail.php answers sent: true.
// 20261111 CL/MMR SST-995: the calendar shows without closed days, and closed days are blocked (map returned nothing).
//                 A start date whose rental would end on a closed day is refused with a message (no onChange loop).
// 20261116 CL/ASR createBooking and updateBooking go with the session cookie (credentials "same-origin"): api.php books only for the customer Mit salg signed in, and approves or rejects only a booking this session made.
//                 When createBooking answers 403 (not signed in as the link's customer), the page says so and goes to mysale.php to sign in, as mybooking.js does.
//                 An approval that api.php refuses or cannot invoice shows that the booking was not confirmed, with its number, and sends no confirmation mail; a refused rejection says so.
//                 The payment status is asked for one request at a time, so the booking is approved or rejected once.
//                 updateBooking counts as done only an answer in JSON with the status it asked for (approved, or deleted for a rejection or a timeout), so a page PHP printed with HTTP 200 instead (an alert from the invoicing) is not taken for a confirmed booking.

// updateBooking answers {"status": ...} when it did what it was asked, and {"error": ...} otherwise: 403 for a booking
// this session did not make or whose customer Mit salg no longer has signed in, 409 for an invoiced order (not
// rejected) or a booking without an order, 500 when the order could not be invoiced.
// The call is done only when the answer is that JSON with the status it asks for: approved for an approval, deleted for
// a rejection or a timeout. An alert levering() or batch_salg() printed before it stopped comes with HTTP 200, and is not.
const updateBooking = async (db, bookingId, status) => {
    const res = await fetch(`api.php?updateBooking&db=${db}`, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            "id": bookingId,
            "status": status
        })
    }).catch(() => null)
    const json = res ? await res.json().catch(() => null) : null
    const answer = json !== null && typeof json === "object" ? json : {} // a page PHP printed instead of JSON is no answer
    const done = status === "approved" ? "approved" : "deleted" // what api.php answers when it did what was asked
    return {"ok": res !== null && res.ok && answer.status === done, "status": res ? res.status : 0, "answer": answer}
}

const productsView = async (main, db) => {
    const products = await fetch(`api.php?getAllProducts&db=${db}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })
    // sort products by product_name
    products.sort((a, b) => {
        if(a.product_name < b.product_name){
            return -1
        }
        if(a.product_name > b.product_name){
            return 1
        }
        return 0
    })
    
    const div = document.createElement('div')
    div.classList.add("grid", "grid-cols-1", "gap-2", "lg:grid-cols-3")
    main.appendChild(div)
    products.forEach(product => {
    div.innerHTML += `<div class="product-container flex flex-wrap justify-between products" id="${product.product_id}">
                        <div id="${product.product_id}" class="product-card flex flex-col items-center rounded-lg shadow md:flex-row border-gray-700 bg-gray-800 hover:bg-gray-700 cursor-pointer basis-1/3 flex-grow m-2">
                            <img id="${product.product_id}" class="object-cover w-full rounded-t-lg h-96 md:h-auto md:w-1/3 md:rounded-none md:rounded-s-lg images" src="../documents/laja_55/varefotos/${product.product_id}" alt="">
                            <div id="${product.product_id}" class="flex flex-col justify-between w-full p-4 leading-normal">
                                <h5 id="${product.product_id}" class="mb-2 text-2xl font-bold tracking-tight text-white md:text-md">${product.product_name}</h5>
                                <p id="${product.product_id}" class="mb-3 font-normal text-gray-400">${product.descript != null ? product.descript : ""}</p>
                                <button id=${product.product_id} class="text-2xl lg:text-sm bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded w-32 lg:w-20">Vælg</button>
                            </div>
                        </div>
                    </div>`
    })

    document.querySelectorAll('.images').forEach((item) => {
        item.onerror = () => {
            item.classList.add("hidden")
        }
    })

    return {"products": products, "div": div}
}

const priceView = async (main, product) => {
    const div = document.createElement('div')
    main.appendChild(div)
    let unit
    if(product.unit.toLowerCase() === "dag"){
        unit = "dage"
    }else{
        unit = "uger"
    }
    // make price danish format with comma instead of dot
    const price = product.price.toString().replace(".", ",")
    div.innerHTML += `<div class="lg:py-32">
  <div class="mx-auto max-w-7xl px-6 lg:px-8">
    <div class="mx-auto mt-20 lg:mt-16 max-w-2xl rounded-3xl ring-1 ring-gray-200 lg:mx-0 lg:flex lg:max-w-none border-gray-700 bg-gray-800">
      <div class="p-8 sm:p-10 lg:flex-auto text-white">
        <h3 class="text-2xl font-bold tracking-tight">${product.product_name}</h3>
        <p class="mt-6 text-xl lg:text-base leading-7">${(product.descript != null) ? product.descript : ""}</p>
        <div class="mt-10 flex items-center gap-x-4">
          <h4 class="flex-none text-2xl lg:text-sm font-semibold leading-6">Vælg ${unit}</h4>
          <div class="h-px flex-auto bg-gray-100"></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-2 mt-4 units">
        </div>
      </div>
      <div class="-mt-2 p-2 lg:mt-0 lg:w-full lg:max-w-md lg:flex-shrink-0">
        <div class="rounded-2xl h-full bg-gray-50 py-10 text-center ring-1 ring-inset ring-gray-900/5 lg:flex lg:flex-col lg:justify-center lg:py-16">
          <div class="mx-auto max-w-xs px-8">
            <p class="text-2xl lg:text-base font-semibold text-gray-600 week-price">${product.periods[0].amount} ${(product.periods[0].amount == 1) ? unit.substring(0, 3) : unit}</p>
            <p class="mt-6 flex items-baseline justify-center gap-x-2">
              <span class="text-5xl font-bold tracking-tight text-gray-900 price">${price}</span>
              <span class="text-xl lg:text-sm font-semibold leading-6 tracking-wide text-gray-600">DKK</span>
            </p>
            <button class="mt-2 lg:mt-0 block w-full rounded-md bg-indigo-600 px-3 py-2 text-center text-xl lg:text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 next">Vælg periode</button>
            <p class="mt-6 text-xl lg:text-xs leading-5 text-gray-600">Du betaler først efter at have valgt hvilken periode du ønsker at booke standen</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>`
    const units = document.querySelector('.units')
    product.periods.forEach(period => {
        if(period.amount == 1){
            units.innerHTML += `<button class="text-xl lg:text-sm bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded weeks" id="${period.amount}">${period.amount} ${unit.substring(0, 3)}</button>`
        }else{
            units.innerHTML += `<button class="text-xl lg:text-sm bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded weeks" id="${period.amount}">${period.amount} ${unit}</button>`
        }
    })
    if(product.choose_periods == 1){
        units.innerHTML += '<button class="bg-blue-500 hover:bg-blue-700 text-white text-xl lg:text-sm font-bold py-2 px-4 rounded weeks choose" id="0">Vælg periode</button>'
        choose = document.querySelector('.choose')
        choose.addEventListener('click', (e) => {
            e.preventDefault()
            weeks = prompt(`Indtast antal ${unit} ${(product.max != 0 || product.max != null) ? "(max " + product.max + ")" : "" }`, 1)
            if(weeks == null || weeks == "" || weeks <= "0" || isNaN(weeks)){
                weeks = parseInt(weeks)
                if(weeks > product.max && product.max != 0){
                    weeks = product.max
                    alert(`Du kan maksimalt booke ${product.max} ${unit}`)
                }
                choose.id = weeks
            }else{
                alert("Du skal skrive et gyldigt nummer")
                weeks = 1
                choose.id = weeks
            }
        })
    }
    return {"div": div}
}

const calendarView = async (main, product, weeks, price, db, userID) => {
  const bookings = await fetch(`api.php?getAllDates=${product.product_id}&db=${db}`,{
    method: "GET",
    headers: {
        "Content-Type": "application/json"
    }
    }).then(response => {
        return response.json()
    })
    const terms = await fetch(`api.php?getTerms&db=${db}`,{
        method: "GET",
        headers: {
            "Content-Type": "application/json"
        }
    }).then(response => {
        return response.json()
    })
    console.log(terms)
    const div = document.createElement('div')
    main.appendChild(div)
    div.innerHTML += `<div class="lg:py-32">
  <div class="mx-auto max-w-7xl lg:px-8">
    <div class="mx-auto mt-16 lg:mt-20 max-w-2xl rounded-3xl ring-1 ring-gray-200 lg:mx-0 lg:flex lg:max-w-none border-gray-700 bg-gray-800">
        <div class="p-5 [&>*]:mx-auto">
            <div id="calendar"></div>
        </div>
        <div class="p-8 sm:p-10 lg:flex-auto text-white">
        <h3 class="text-2xl font-bold tracking-tight">${product.product_name}</h3>
        <p class="mt-6 text-base leading-7">${(product.descript != null) ? product.descript : ""}</p>
        <div class="grid grid-cols-2">
        <p class="mt-3 text-xl lg:text-base leading-7 col-span-1">uger: </p>
        <p class="mt-3 text-xl lg:text-base leading-7 col-span-1">${weeks}</p>
        <div class="flex items-center gap-x-4 col-span-2 mt-4">
            <div class="h-px flex-auto bg-gray-100"></div>
        </div>
        <p class="mt-3 text-xl lg:text-base leading-7 col-span-1">Pris: </p>
        <p class="mt-3 text-xl lg:text-base leading-7 col-span-1">${price} kr.</p>
        </div>
            <div class="mt-4">
      <label class="inline-flex items-center">
        <input type="checkbox" id="terms" class="form-checkbox h-5 w-5 text-blue-600">
        <span class="ml-2 text-white text-xl lg:text-sm">Jeg accepterer <a href="${terms.terms}" class="text-blue-500 hover:text-blue-400">handelsbetingelserne</a></span>
      </label>
    </div>
        <div class="flex justify-end mt-2 lg:mt-0">
            <button class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-xl lg:text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 w-32 lg:w-24 rental">lej stand</button>
        </div>
        </div>
    </div>
  </div>
</div>`

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

    // SST-995: without closed days the API answers {"success":false,...}, so only a list holds dates;
    // the formatted date must be returned, or flatpickr gets a list of undefined and blocks nothing.
    const closedDates = Array.isArray(getClosedDates) ? getClosedDates.map(date => {
        date = new Date(date.date * 1000)
        return date.getFullYear() + "-" + ("0" + (date.getMonth() + 1)).slice(-2) + "-" + ("0" + date.getDate()).slice(-2)
    }) : []

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
            const endDate = new Date(date)
            endDate.setDate(date.getDate() + (weeks*7)) // Corrected to always add weeks*7 days
        
            const allItemIds = [...new Set(bookings.map(b => b.id))] // Get unique item IDs
            console.log(allItemIds)
            const isDateAvailable = allItemIds.some(itemId => {
                // Check if this specific item is reserved during the period
                const isItemReserved = bookings.some(res => {
                    if (res.id !== itemId) return false
                    
                    // Convert Unix timestamps to Date objects
                    const resFrom = new Date(res.rt_from * 1000)
                    const resTo = new Date(res.rt_to * 1000)
                    
                    // Check if this reservation overlaps with requested period
                    return (date >= resFrom && date <= resTo) ||
                        (endDate >= resFrom && endDate <= resTo) ||
                        (date <= resFrom && endDate >= resTo)
                })
                // Add this available date to our tracking array
                if (!isItemReserved) {
                    availableDatesById.push([itemId, date.toISOString().split('T')[0]])
                }
                // If item is not reserved, it's available
                return !isItemReserved
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
                
                if(product.unit.toLowerCase() === "dag"){
                    endDate.setDate(startDate.getDate() + parseInt(weeks))
                }else{
                    endDate.setDate(startDate.getDate() + weeks*7)
                }

                // SST-995: an end date on a closed day is not allowed. flatpickr drops a disabled date from
                // setDate, which calls onChange again with one date and loops, so stop here instead.
                const endDay = endDate.getFullYear() + "-" + ("0" + (endDate.getMonth() + 1)).slice(-2) + "-" + ("0" + endDate.getDate()).slice(-2)
                if (closedDates.includes(endDay)) {
                    instance.clear(false)
                    alert("Slutdatoen " + endDay + " er en lukkedag. Vælg en anden startdato.")
                    return
                }

                // Update the calendar selection to the new range
                instance.setDate([startDate, endDate], true)
            }

            // Format startDate and endDate to YYYY-MM-DD
            startDate = startDate.toISOString().slice(0, 10)
            if (endDate) endDate = endDate.toISOString().slice(0, 10)
        }
  })
        const rental = document.querySelector('.rental')
        rental.addEventListener('click', async (e) => {
            const selectedDates = datePick.selectedDates
            let startDate, endDate
            if (!document.getElementById('terms').checked) {
                alert("Du skal acceptere handelsbetingelserne");
                return;
            }

            if (selectedDates.length === 2) {
                rental.disabled = true
                setTimeout(() => {
                    rental.disabled = false
                }, 2000);
                startDate = selectedDates[0]
                endDate = selectedDates[1]
            } else if (selectedDates.length === 1) {
                rental.disabled = true
                setTimeout(() => {
                    rental.disabled = false
                }, 2000);
                startDate = selectedDates[0]
                endDate = new Date(startDate)
                endDate.setDate(startDate.getDate() + weeks*7)
            }else{
                console.error("No dates selected")
                alert("Du skal vælge en dato")
                return
            }

            let offset = startDate.getTimezoneOffset()
            let localDate = new Date(startDate.getTime() - offset * 60 * 1000)
            startDate = localDate.toISOString().split('T')[0]

            offset = endDate.getTimezoneOffset()
            localDate = new Date(endDate.getTime() - offset * 60 * 1000)
            endDate = localDate.toISOString().split('T')[0]

            const shuffleArray = array => {
                for (let i = array.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [array[i], array[j]] = [array[j], array[i]]; // Swap elements
                }
                return array;
            }
            // get id from availableDatesById based on startDate
            const shuffledArray = shuffleArray(availableDatesById);
            const id = shuffledArray.find(([id, date]) => date === startDate)[0]
            
            data = {
                "product_id": product.id,
                "start_date": startDate,
                "end_date": endDate,
                "weeks": weeks,
                "price": price * 0.8,
                "item_id": id,
                "cust_id": userID,
                "sku": product.sku,
                "unit": product.unit,
            }

            const booking = await fetch(`api.php?createBooking&db=${db}`,{
                method: "POST",
                credentials: "same-origin", // api.php books for the customer Mit salg signed in in this session
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            const response = await booking.json().catch(() => ({}))
            if (booking.status === 403) {
                // Mit salg has not signed this customer in (or has signed out): sign in through the link, as mybooking.js does
                alert("Du er ikke logget ind på Mit salg. Log ind, og book standen igen.")
                window.location.href = `mysale.php?id=${encodeURIComponent(new URLSearchParams(window.location.search).get('id') || '')}`
                return
            }
            // api.php refuses a stand booked or blocked since the calendar was drawn (409), and a booking it cannot make (400).
            // Then there is no hold to pay for: say so and stop before the payment link.
            if (!booking.ok || !response.id) {
                alert(booking.status === 409
                    ? "Den valgte periode er ikke længere ledig. Opdater siden, og vælg en anden periode."
                    : "Bookingen kunne ikke oprettes. Kontakt butikken.")
                return
            }
            const bookingId = response.id
            const loading = document.querySelector("#loading")
            loading.style.display = "flex"
            const quickRes = await fetch("vibrantPaymentLink.php", {
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
            if (!quickRes || quickRes.error || !quickRes.url) {
                // No tenant Vibrant key or Vibrant issued no link: stop instead of opening "undefined".
                loading.style.display = "none"
                // release the hold; if api.php refuses, the hold lapses when it expires
                await updateBooking(db, bookingId, "rejected")
                alert("Betalingslinket kunne ikke oprettes. Kontakt butikken.")
                return
            }

            // open payment window in a new windows
            window.open(quickRes.url, '', 'height=700,width=500')

            // get status every other second for 180 seconds, one request at a time: the booking is approved or rejected once
            let i = 0
            let polling = false
            let settled = false
            let interval
            const pollPayment = async () => {
                const status = await fetch(`vibrantPaymentIntent.php?id=${quickRes.id}`,{
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        "db": db
                    })
                }).then(response => {
                    return response.json()
                }).catch(() => ({})) // no answer this time: ask again at the next tick
                if(status.state === "succeeded"){
                    settled = true
                    clearInterval(interval)
                    loading.style.display = "none"
                    // approving invoices the order; say thank you and mail the confirmation only when api.php did it
                    const approval = await updateBooking(db, bookingId, "approved")
                    if (!approval.ok) {
                        main.innerHTML = `
                            <div class="w-2/3 mx-auto text-white text-center mt-4">
                                <h3>Betalingen er gennemført, men bookingen kunne ikke bekræftes.</h3>
                                <p>Kontakt butikken, og oplys booking nr. ${Number(bookingId)}.</p>
                            </div>
                        `
                        return
                    }
                    main.innerHTML = `
                        <div class="w-2/3 mx-auto text-white text-center mt-4">
                            <h3>Tak for din bestilling!</h3>
                            <p id="mailStatus">Sender ordrebekræftelsen på mail ...</p>
                        </div>
                    `
                    // sendMail.php answers sent: true only when the confirmation went out. Say it was mailed only then.
                    const emailRes = await fetch(`sendMail.php`,{
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            "booking_id": bookingId,
                            "price": price,
                            "product_id": product.id,
                            "start_date": startDate,
                            "end_date": endDate,
                            "db": db,
                            "user": userID
                        })
                    }).then(response => {
                        return response.json()
                    }).catch(() => ({}))
                    document.querySelector("#mailStatus").textContent = emailRes && emailRes.sent === true
                        ? "Vi har sendt en ordrebekræftelse mail med ordre."
                        : "Ordrebekræftelsen kunne ikke sendes på mail. Kontakt butikken."
                }else if(status.state === "failed"){
                    settled = true
                    clearInterval(interval)
                    loading.style.display = "none"
                    alert("Betaling afvist")
                    const rejection = await updateBooking(db, bookingId, "rejected")
                    if (!rejection.ok) {
                        alert(`Bookingen kunne ikke annulleres. Kontakt butikken, og oplys booking nr. ${Number(bookingId)}.`)
                    }
                    /* location.reload() */
                }else if(i >= 95){
                    settled = true
                    clearInterval(interval)
                    loading.style.display = "none"
                    alert("Timeout")
                    const rejection = await updateBooking(db, bookingId, "timeout")
                    if (!rejection.ok) {
                        alert(`Bookingen kunne ikke annulleres. Kontakt butikken, og oplys booking nr. ${Number(bookingId)}.`)
                    }
                    location.reload()
                }
            }
            interval = setInterval(() => {
                i++
                if (polling || settled) { // the previous status request is still waiting, or the booking is settled
                    return
                }
                polling = true
                pollPayment().finally(() => {
                    polling = false
                })
            }, 2000)
        })
}

const calculatePrice = (product, unitAmount) => {
    const productPrice = product.price * 0.8
    let paidWeeks = unitAmount
    let discountPeriods = []
    let discountAmount = []
    let i = -1
    let discount = 0.00
    let discountAmountValue = 0.00
    let rabatart = ""
    let price = 0
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
                price = productPrice * paidWeeks
                discountAmountValue = (productPrice * discount) / 100
                discountAmountValue = discountAmountValue * paidWeeks
                rabatart = "percent"
            } else {
                discount = discountAmount[i]
                price = productPrice * paidWeeks
                discountAmountValue = discount * paidWeeks
                rabatart = "amount"
            }
        } else {
            price = productPrice * paidWeeks
            discountAmountValue = 0.00
            discount = 0.00
            rabatart = ""
        }
    } else {
        price = productPrice * paidWeeks
        discountAmountValue = 0.00
        discount = 0.00
        rabatart = ""
    }
    console.log("paid weeks: ", paidWeeks, "", "price: ", price, "discount: ", discount, "discountAmountValue: ", discountAmountValue, "rabatart: ", rabatart)
    sum = (price - discountAmountValue) * 1.25
    return sum
}

const main = async () => {
    // get url parameters
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
                let unitAmount = product.periods[0].amount
                let price = calculatePrice(product, unitAmount)
                price = Math.round(price)
                const res = await priceView(main, product)
                const unitButtons = document.querySelectorAll('.weeks')
                unitButtons.forEach(button => {
                    button.addEventListener('click', (e) => {
                        e.preventDefault()
                        unitAmount = e.target.id
                    })
                })
                const weeks = document.querySelectorAll('.weeks')
                const weekPrice = document.querySelector('.week-price')
                const priceHTML = document.querySelector('.price')
                const next = document.querySelector('.next')
                let sum = product.price
                next.addEventListener('click', (e) => {
                    res.div.remove()
                    calendarView(main, product, unitAmount, sum, db, userID)

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

                        sum = calculatePrice(product, id)
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