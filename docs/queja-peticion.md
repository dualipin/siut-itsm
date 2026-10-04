# Queja - Petición

Debe mostrar archivos adjuntos relacionados a la petición. Cada agremiado puede sugerir de una hasta 4 peticiones. Las peticiones son anuales y hay una fecha limite (cuando cierran las peticiones).


> Nota. Estoy usando laravel con filament y spatie para manejar los archivos


Sugiero esta estructura

```mermaid
erDiagram
    AnnualPetition ||--o{ PetitionRequest : "has many"
    Member ||--o{ PetitionRequest : "submits"

    AnnualPetition {
        bigint id PK
        int year UK "unique"
        date deadline
    }

    PetitionRequest {
        bigint id PK
        bigint annual_petition_id FK
        varchar(30) curp
        text agremiado_name null
        text proposal
    }
```