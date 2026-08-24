const dateFormatter = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
})

export function formatDate(value) {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : dateFormatter.format(date)
}

export function formatAmount(amount, currencyCode) {
  const [integerPart, fraction = ''] = String(amount).split('.')
  const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const decimal = fraction ? `.${fraction}` : ''
  return `${currencyCode} ${groupedInteger}${decimal}`
}
