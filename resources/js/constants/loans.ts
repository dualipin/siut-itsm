export type MonthlyRateValue = '6' | '7.5' | '8' | '9.5' | '10'

export const MONTHLY_RATE_OPTIONS: Array<{ value: MonthlyRateValue; label: string }> = [
    { value: '6', label: 'Agremiado / Ahorrador' },
    { value: '7.5', label: 'Agremiado / No ahorrador' },
    { value: '8', label: 'No agremiado / Ahorrador' },
    { value: '9.5', label: 'No agremiado / No ahorrador' },
    { value: '10', label: 'Directivos no gremiales' },
]
