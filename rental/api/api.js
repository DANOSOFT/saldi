// Getting information from php file
const apiUrl = "rental.php?"

const fetchJson = async (url, options) => {
  const response = await fetch(url, options)
  const json = await response.json()
  return json
}

export const getCustomers = async (month, year) => {
  const url = `${apiUrl}customers&month=${month}&year=${year}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getCustomer = async (id) => {
  const url = `${apiUrl}customer=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllCustomers = async () => {
  const url = `${apiUrl}getAllCustomers`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllItems = async () => {
  const url = `${apiUrl}getAllItems`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllItemsFromId = async (id) =>{
  const url = `${apiUrl}getAllItemsFromId=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "content-type": "application/json" }
  })
}

export const createBooking = async (data) => {
  const url = `${apiUrl}createBooking`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const updateBooking = async (data) => {
  const url = `${apiUrl}updateBooking`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const deleteBooking = async (id) => {
  const url = `${apiUrl}deleteBooking=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getBooking = async (id) => {
  const url = `${apiUrl}getBooking=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getBookingByCustomer = async (id) => {
  const url = `${apiUrl}getBookingByCustomer=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getItem = async (id) => {
  const url = `${apiUrl}getItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const deleteItem = async (id) => {
  const url = `${apiUrl}deleteItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const updateItem = async (data) => {
  const url = `${apiUrl}updateItem`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getProducts = async () => {
  const url = `${apiUrl}products`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getProductCount = async (id) => {
  const url = `${apiUrl}productCount=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getProductInfo = async () => {
  const url = `${apiUrl}productInfo`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getProductInfos = async (month, year) => {
  const url = `${apiUrl}productInfo&month=${month}&year=${year}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const updateQty = async (id, qty) => {
  const url = `${apiUrl}updateQty=${id}qty=${qty}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
}

export const createOrder = async (data) => {
  const url = `${apiUrl}createOrder`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const createItem = async (data) => {
  const url = `${apiUrl}createItem`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getClosedDays = async () => {
  const url = `${apiUrl}getClosedDays`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const insertClosedDay = async (data) => {
  const url = `${apiUrl}insertClosedDay`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const deleteClosedDay = async (id) => {
  const url = `${apiUrl}deleteClosedDay=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getItemBookings = async (id) => {
  const url = `${apiUrl}getItemBookings=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllProductNames = async () => {
  const url = `${apiUrl}getAllProductNames`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getSettings = async () => {
  const url = `${apiUrl}getSettings`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const updateSettings = async (data) => {
  const url = `${apiUrl}updateSettings`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getBookings = async (id) => {
  const url = `${apiUrl}getBookings`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const createReservation = async (data) => {
  const url = `${apiUrl}createReservation`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getReservations = async () => {
  const url = `${apiUrl}getReservations`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getReservationsByItem = async (id) => {
  const url = `${apiUrl}getReservationsByItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const deleteReservation = async (id) => {
  const url = `${apiUrl}deleteReservation=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const deleteReservationByItem = async (id) => {
  const url = `${apiUrl}deleteReservationByItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const editReservationComment = async (data) => {
  const url = `${apiUrl}editReservationComment`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const editReservationDates = async (data) => {
  const url = `${apiUrl}editReservationDates`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getXCSRFToken = async (data) => {
  const url = `${apiUrl}getXCSRFToken`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getBookingsByCust = async (id) => {
  const url = `${apiUrl}getBookingsByCust=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllProducts = async () => {
  const url = `${apiUrl}getAllProducts`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const updateRemoteProduct = async (data) => {
  const url = `${apiUrl}updateRemoteProduct`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const updateMail = async (data) => {
  const url = `${apiUrl}updateMail`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const getMailInfo = async () => {
  const url = `${apiUrl}getMailInfo`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getBookingsForItems = async () => {
  const url = `${apiUrl}getBookingsForItems`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
}

export const getBookingsForItemsByType = async (type) => {
  const url = `${apiUrl}getBookingsForItemsByType=${type}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
}

export const getPayment = async () => {
  const url = `${apiUrl}getPayment`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
}

export const updatePayment = async (data) => {
  const url = `${apiUrl}updatePayment`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  })
}

export const getRemoteLink = async () => {
  const url = `${apiUrl}getRemoteLink`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
}