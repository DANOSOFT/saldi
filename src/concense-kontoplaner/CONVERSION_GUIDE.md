# Kontoplan Conversion Guide

## Overview
This document explains how to convert EC-SEL kontoplan format to your system's format.

## Key Differences

### File Format
- **EC-SEL Format**: CSV with comma separator, columns: `Kontonr.`, `Kontonavn`, `Moms`, `Type`
- **Your Format**: Tab-delimited CSV, columns: `kontonr`, `beskrivelse`, `kontotype`, `momskode`, `fra_konto`

### VAT Code Mapping
| EC-SEL Code | Your Code | Description |
|-------------|-----------|-------------|
| U25 | S1 | Sales with 25% VAT |
| UVC | (empty) | Sales to non-EU countries |
| UEUV | (empty) | Sales to EU countries |
| UEUY | (empty) | Services to EU countries |
| I25 | K1 | Purchases with 25% VAT |
| IEUV | (empty) | Purchases from EU countries |
| IEUY | (empty) | Services from EU countries |
| IVV | (empty) | Purchases from non-EU countries |
| IVY | (empty) | Services from non-EU countries |
| REP | (empty) | Restaurant expenses |
| HREP | (empty) | Hotel expenses |
| OBPK | (empty) | Reverse charge |

### Account Type Mapping
| EC-SEL Type | Your Type | Description |
|-------------|-----------|-------------|
| D | D | Detail accounts |
| A | A | Asset accounts |
| P | P | Passive accounts |
| H | H | Header accounts |
| SUM | Z | Summary accounts |
| Overskrift | H | Header accounts |
| R | R | Result accounts |

### Account Number Mapping
The converter includes mappings for common account numbers:

#### Revenue Accounts (1000-1999)
- 1010 → 1010 (Sales with VAT)
- 1020 → 1020 (Export sales)
- 1030 → 1030 (Sales without VAT)

#### Cost Accounts (2000-2999)
- 1310 → 2010 (Direct costs)
- 1315 → 1015 (Freight costs)
- 1330 → 2010 (Material consumption)

#### Personnel Costs (7000-7999)
- 2210 → 7010 (Salaries)
- 2215 → 7165 (Employer pension)
- 2223 → 7055 (ATP)

#### Operating Expenses (4000-6999)
- 3410 → 4010 (Rent with VAT)
- 3420 → 4030 (Utilities)
- 3600 → 6010 (Office supplies)
- 3620 → 6020 (Telephone)
- 3640 → 6130 (Accounting services)
- 3650 → 6085 (Insurance)

#### Financial Accounts (9000-9999)
- 4310 → 9070 (Bank interest income)
- 4410 → 9250 (Bank interest expense)

#### Asset Accounts (10000-19999)
- 5001 → 10305 (Goodwill)
- 5101 → 11110 (Buildings)
- 5221 → 11505 (Equipment)
- 5231 → 11610 (IT equipment)
- 5600 → 16110 (Debtors)
- 5810 → 18010 (Cash)
- 5820 → 18025 (Bank account)

#### Liability Accounts (20000-29999)
- 6110 → 20010 (Share capital)
- 6130 → 20810 (Retained earnings)
- 6610 → 26830 (Corporate tax)
- 6800 → 26010 (Creditors)
- 6900 → 27110 (VAT)

## Usage Instructions

### 1. Run the Converter
```bash
python3 kontoplan_converter.py "KONTOPLANER - EC-SEL.csv" "converted_kontoplan.csv"
```

### 2. Review the Output
- Check that all accounts have been converted correctly
- Verify VAT codes are mapped properly
- Ensure account types are correct
- Review parent account assignments

### 3. Manual Adjustments
You may need to manually adjust:
- Account numbers that don't have direct mappings
- Parent account relationships (fra_konto)
- Account descriptions for better clarity
- VAT codes for special cases

### 4. Import to Your System
Once converted, the file should be compatible with your system's import functionality.

## Troubleshooting

### Common Issues
1. **Missing Account Mappings**: Add new mappings to the `account_mapping` dictionary
2. **Incorrect VAT Codes**: Update the `vat_mapping` dictionary
3. **Wrong Parent Accounts**: Modify the `determine_parent_account` method
4. **Encoding Issues**: Ensure files are saved with UTF-8 encoding

### Validation Checklist
- [ ] All accounts have valid account numbers
- [ ] VAT codes are correctly mapped
- [ ] Account types are appropriate
- [ ] Parent accounts are logically assigned
- [ ] File format matches your system requirements

## Support
For additional support or custom mappings, contact your system administrator.
