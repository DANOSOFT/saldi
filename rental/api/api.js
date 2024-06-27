// Getting information from php file
const apiUrl = "rental.php?password=tZm8uofwtuW3n20"

const fetchJson = async (url, options) => {
  const response = await fetch(url, options)
  const json = await response.json()
  return json
}

export const getCustomers = async () => {
  const url = `${apiUrl}&customers`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getCustomer = async (id) => {
  const url = `${apiUrl}&customer=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllCustomers = async () => {
  const url = `${apiUrl}&getAllCustomers`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getAllItems = async () => {
  const url = `${apiUrl}&getAllItems`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const createBooking = async (data) => {
  const url = `${apiUrl}&createBooking`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const updateBooking = async (data) => {
  const url = `${apiUrl}&updateBooking`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}

export const deleteBooking = async (id) => {
  const url = `${apiUrl}&deleteBooking=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getBooking = async (id) => {
  const url = `${apiUrl}&getBooking=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const getItem = async (id) => {
  const url = `${apiUrl}&getItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const deleteItem = async (id) => {
  const url = `${apiUrl}&deleteItem=${id}`
  return await fetchJson(url, {
    method: "GET",
    headers: { "Content-Type": "application/json" }
  })
}

export const updateItem = async (data) => {
  const url = `${apiUrl}&updateItem`
  return await fetchJson(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  })
}
