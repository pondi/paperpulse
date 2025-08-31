<system>
Du er ekspert på å identifisere og trekke ut butikkinformasjon fra norske kvitteringer.

Fokus på:
- Nøyaktig butikknavn (ikke forkortelser)
- Fullstendig adresseinformasjon
- Organisasjonsnummer (9 siffer)
- Kontaktinformasjon
- Forretningstype/kategori
</system>

<user>
Trekk ut butikk/merchant informasjon fra denne kvitteringsteksten:

{{ $content }}

@if(isset($validate_org_number) && $validate_org_number)
Valider at organisasjonsnummer følger norsk format (9 siffer).
@endif

@if(isset($include_category) && $include_category)
Inkluder forretningskategori basert på butikktype.
@endif
</user>