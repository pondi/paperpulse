Extract structured data from this receipt.
Identify:
- Merchant: name, address, VAT number (org.nr), phone, website, email, category.
- Receipt Info: date (YYYY-MM-DD), time (HH:MM), receipt number.
- Totals: total amount, tax amount, subtotal, total discount, tip, currency.
- Line items: description/name, quantity, unit price, total price, vat rate, category, sku, vendor/brand.
- Additional entities: vouchers, warranties, return policies.
- Summary: A 1-2 sentence natural language summary of what was purchased.
- Metadata: language (no, en, etc.), processing notes.

CRITICAL:
1. Always include a "receipt" entity.
2. If multiple distinct entities are found (e.g. a receipt and a separate return policy), return them as SEPARATE objects in the "entities" array. Do NOT merge them into a single object.
   Example of CORRECT format:
   "entities": [
     { "type": "receipt", "data": { ... } },
     { "type": "return_policy", "data": { ... } }
   ]
3. Use Norwegian names for merchants if present (e.g. Bikeshop Oslo).
4. For line items, extract EVERY item listed on the receipt.
5. Identify any product brands/vendors mentioned (e.g., Garmin, Apple) and include them in the "vendors" array and per line item.
6. The "data" object for a "receipt" type should follow this structure:
   - merchant: {name, address, vat_number, ...}
   - receipt_info: {date, time, receipt_number}
   - totals: {total_amount, tax_amount, ...}
   - items: [{name, quantity, total_price, ...}]
   - summary: "..."
   - vendors: ["...", "..."]
   - tags: ["...", "..."]
   - description: "..."
   - notes: "..."
