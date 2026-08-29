export interface SyndicateConfig {
  name: string
  acronym: string
  email: string
  phone: string
  address: string
  socialMedia: {
    facebook: string
    whatsapp: string
  }
  logo: {
    src: string
    alt: string
  }
  slogan: string
}