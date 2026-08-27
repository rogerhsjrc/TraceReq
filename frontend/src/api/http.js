/**
 * An HTTP failure that preserves the response status and parsed response body.
 */
export class HttpError extends Error {
  constructor(message, status, body = null) {
    super(message)
    this.name = 'HttpError'
    this.status = status
    this.body = body
  }
}

/**
 * Laravel validation failure with errors grouped by input field.
 */
export class ValidationError extends HttpError {
  constructor(message, errors, body = null) {
    super(message, 422, body)
    this.name = 'ValidationError'
    this.errors = errors
  }
}

function parseResponseBody(text) {
  if (!text) return null

  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

function errorMessage(body, fallback) {
  return body && typeof body === 'object' && typeof body.message === 'string'
    ? body.message
    : fallback
}

/**
 * Sends a JSON API request using relative URLs.
 * Network failures intentionally remain native errors so callers can report them.
 */
export async function http(url, { method = 'GET', body, signal } = {}) {
  const headers = new Headers({ Accept: 'application/json' })
  const options = { method, headers, signal }

  if (body !== undefined) {
    headers.set('Content-Type', 'application/json')
    options.body = JSON.stringify(body)
  }

  const response = await fetch(url, options)
  const responseBody = parseResponseBody(await response.text())

  if (!response.ok) {
    const message = errorMessage(responseBody, `Request failed with status ${response.status}.`)

    if (
      response.status === 422 &&
      responseBody &&
      typeof responseBody === 'object' &&
      responseBody.errors &&
      typeof responseBody.errors === 'object'
    ) {
      throw new ValidationError(message, responseBody.errors, responseBody)
    }

    throw new HttpError(message, response.status, responseBody)
  }

  return responseBody
}
