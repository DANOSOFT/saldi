#!/usr/bin/env python3
"""
Kontoplan Converter
Converts EC-SEL kontoplan format to your system's format
"""

import csv
import sys
from typing import Dict, List, Tuple

class KontoplanConverter:
    def __init__(self):
        # VAT code mapping from EC-SEL format to your format
        self.vat_mapping = {
            'U25': 'S1',      # Sales with 25% VAT
            'UVC': '',        # Sales to non-EU countries (no VAT)
            'UEUV': '',       # Sales to EU countries (no VAT)
            'UEUY': '',       # Services to EU countries (no VAT)
            'I25': 'K1',      # Purchases with 25% VAT
            'IEUV': '',       # Purchases from EU countries (no VAT)
            'IEUY': '',       # Services from EU countries (no VAT)
            'IVV': '',        # Purchases from non-EU countries (no VAT)
            'IVY': '',        # Services from non-EU countries (no VAT)
            'REP': '',        # Restaurant expenses (no VAT deduction)
            'HREP': '',       # Hotel expenses (no VAT deduction)
            'OBPK': '',       # Reverse charge (no VAT)
            '': ''            # No VAT
        }
        
        # Account type mapping
        self.type_mapping = {
            'D': 'D',         # Detail accounts
            'A': 'A',         # Asset accounts
            'P': 'P',         # Passive accounts
            'H': 'H',         # Header accounts
            'SUM': 'Z',       # Summary accounts
            'Overskrift': 'H', # Header accounts
            'R': 'R'          # Result accounts
        }
        
        # Account number mapping for common accounts
        self.account_mapping = {
            # Sales accounts
            '1010': '1010',   # Sales with VAT
            '1020': '1020',   # Export sales
            '1030': '1030',   # Sales without VAT
            
            # Cost accounts
            '1310': '2010',   # Direct costs
            '1315': '1015',   # Freight costs
            '1330': '2010',   # Material consumption
            
            # Personnel costs
            '2210': '7010',   # Salaries
            '2215': '7165',   # Employer pension
            '2223': '7055',   # ATP
            
            # Operating expenses
            '3410': '4010',   # Rent with VAT
            '3420': '4030',   # Utilities
            '3600': '6010',   # Office supplies
            '3620': '6020',   # Telephone
            '3640': '6130',   # Accounting services
            '3650': '6085',   # Insurance
            
            # Financial accounts
            '4310': '9070',   # Bank interest income
            '4410': '9250',   # Bank interest expense
            
            # Asset accounts
            '5001': '10305',  # Goodwill
            '5101': '11110',  # Buildings
            '5221': '11505',  # Equipment
            '5231': '11610',  # IT equipment
            '5600': '16110',  # Debtors
            '5810': '18010',  # Cash
            '5820': '18025',  # Bank account
            
            # Liability accounts
            '6110': '20010',  # Share capital
            '6130': '20810',  # Retained earnings
            '6610': '26830',  # Corporate tax
            '6800': '26010',  # Creditors
            '6900': '27110',  # VAT
            
            # Additional mappings for missing accounts
            '1310': '2010',   # Direct costs with VAT
            '1315': '1015',   # Freight with VAT
            '1330': '2010',   # Material consumption
            '2210': '7010',   # Salaries
            '2215': '7165',   # Employer pension
            '2223': '7055',   # ATP
            '3410': '4010',   # Rent with VAT
            '3420': '4030',   # Utilities
            '3600': '6010',   # Office supplies
            '3620': '6020',   # Telephone
            '3640': '6130',   # Accounting services
            '3650': '6085',   # Insurance
            '4310': '9070',   # Bank interest income
            '4410': '9250',   # Bank interest expense
            '5001': '10305',  # Goodwill
            '5101': '11110',  # Buildings
            '5221': '11505',  # Equipment
            '5231': '11610',  # IT equipment
            '5600': '16110',  # Debtors
            '5810': '18010',  # Cash
            '5820': '18025',  # Bank account
            '6110': '20010',  # Share capital
            '6610': '26830',  # Corporate tax
            '6800': '26010',  # Creditors
            '6900': '27110',  # VAT
        }

    def convert_vat_code(self, ec_sel_vat: str) -> str:
        """Convert EC-SEL VAT code to your system's VAT code"""
        return self.vat_mapping.get(ec_sel_vat, '')

    def convert_account_type(self, ec_sel_type: str) -> str:
        """Convert EC-SEL account type to your system's account type"""
        return self.type_mapping.get(ec_sel_type, 'D')

    def convert_account_number(self, ec_sel_number: str) -> str:
        """Convert EC-SEL account number to your system's account number"""
        return self.account_mapping.get(ec_sel_number, ec_sel_number)

    def convert_kontoplan(self, input_file: str, output_file: str):
        """Convert EC-SEL kontoplan to your system's format"""
        converted_accounts = []
        
        with open(input_file, 'r', encoding='utf-8') as infile:
            reader = csv.DictReader(infile)
            
            for row in reader:
                # Extract data from EC-SEL format
                kontonr = row['Kontonr.'].strip()
                beskrivelse = row['Kontonavn'].strip()
                moms = row['Moms'].strip()
                kontotype = row['Type'].strip()
                
                # Convert to your format
                converted_kontonr = self.convert_account_number(kontonr)
                converted_momskode = self.convert_vat_code(moms)
                converted_kontotype = self.convert_account_type(kontotype)
                
                # Determine fra_konto (parent account) based on account hierarchy
                fra_konto = self.determine_parent_account(converted_kontonr)
                
                converted_accounts.append({
                    'kontonr': converted_kontonr,
                    'beskrivelse': beskrivelse,
                    'kontotype': converted_kontotype,
                    'momskode': converted_momskode,
                    'fra_konto': fra_konto
                })
        
        # Write converted kontoplan
        with open(output_file, 'w', encoding='utf-8', newline='') as outfile:
            fieldnames = ['kontonr', 'beskrivelse', 'kontotype', 'momskode', 'fra_konto']
            writer = csv.DictWriter(outfile, fieldnames=fieldnames, delimiter='\t')
            writer.writeheader()
            writer.writerows(converted_accounts)
        
        print(f"Conversion completed. Output saved to: {output_file}")
        print(f"Converted {len(converted_accounts)} accounts")

    def determine_parent_account(self, account_number: str) -> str:
        """Determine parent account based on account number hierarchy"""
        if not account_number.isdigit():
            return '0'
        
        num = int(account_number)
        
        # Define account ranges and their parent accounts
        if 1000 <= num < 2000:
            return '100'  # Revenue accounts
        elif 2000 <= num < 3000:
            return '1999'  # Cost of goods sold
        elif 3000 <= num < 4000:
            return '3998'  # External costs
        elif 4000 <= num < 5000:
            return '3999'  # Local costs
        elif 5000 <= num < 6000:
            return '4999'  # Sales and distribution costs
        elif 6000 <= num < 7000:
            return '5999'  # Other costs
        elif 7000 <= num < 8000:
            return '6999'  # Personnel costs
        elif 8000 <= num < 9000:
            return '7999'  # Depreciation
        elif 9000 <= num < 10000:
            return '8999'  # Financial items
        elif 10000 <= num < 20000:
            return '10001'  # Assets
        elif 20000 <= num < 30000:
            return '19999'  # Equity and liabilities
        else:
            return '0'

def main():
    if len(sys.argv) != 3:
        print("Usage: python kontoplan_converter.py <input_file> <output_file>")
        print("Example: python kontoplan_converter.py 'KONTOPLANER - EC-SEL.csv' 'converted_kontoplan.csv'")
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    converter = KontoplanConverter()
    converter.convert_kontoplan(input_file, output_file)

if __name__ == "__main__":
    main()
