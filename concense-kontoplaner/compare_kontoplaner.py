#!/usr/bin/env python3
"""
Kontoplan Comparison Tool
Compares original and converted kontoplan files to validate conversion
"""

import csv
import sys
from collections import defaultdict

def compare_kontoplaner(original_file: str, converted_file: str):
    """Compare original and converted kontoplan files"""
    
    print("=== KONTOPLAN CONVERSION VALIDATION ===\n")
    
    # Read original file
    original_accounts = {}
    with open(original_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            kontonr = row['Kontonr.'].strip()
            original_accounts[kontonr] = {
                'name': row['Kontonavn'].strip(),
                'vat': row['Moms'].strip(),
                'type': row['Type'].strip()
            }
    
    # Read converted file
    converted_accounts = {}
    with open(converted_file, 'r', encoding='utf-8') as f:
        reader = csv.DictReader(f, delimiter='\t')
        for row in reader:
            kontonr = row['kontonr'].strip()
            converted_accounts[kontonr] = {
                'name': row['beskrivelse'].strip(),
                'vat': row['momskode'].strip(),
                'type': row['kontotype'].strip(),
                'parent': row['fra_konto'].strip()
            }
    
    print(f"Original accounts: {len(original_accounts)}")
    print(f"Converted accounts: {len(converted_accounts)}")
    print()
    
    # Check for missing accounts
    missing_in_converted = set(original_accounts.keys()) - set(converted_accounts.keys())
    if missing_in_converted:
        print(f"⚠️  Missing accounts in converted file: {len(missing_in_converted)}")
        for acc in sorted(missing_in_converted):
            print(f"   {acc}: {original_accounts[acc]['name']}")
        print()
    
    # Check VAT code mappings
    print("=== VAT CODE MAPPINGS ===")
    vat_mappings = defaultdict(list)
    for kontonr in original_accounts:
        if kontonr in converted_accounts:
            orig_vat = original_accounts[kontonr]['vat']
            conv_vat = converted_accounts[kontonr]['vat']
            vat_mappings[f"{orig_vat} → {conv_vat}"].append(kontonr)
    
    for mapping, accounts in vat_mappings.items():
        print(f"{mapping}: {len(accounts)} accounts")
        if len(accounts) <= 5:  # Show details for small groups
            for acc in accounts[:5]:
                print(f"   {acc}: {original_accounts[acc]['name']}")
        print()
    
    # Check account type mappings
    print("=== ACCOUNT TYPE MAPPINGS ===")
    type_mappings = defaultdict(list)
    for kontonr in original_accounts:
        if kontonr in converted_accounts:
            orig_type = original_accounts[kontonr]['type']
            conv_type = converted_accounts[kontonr]['type']
            type_mappings[f"{orig_type} → {conv_type}"].append(kontonr)
    
    for mapping, accounts in type_mappings.items():
        print(f"{mapping}: {len(accounts)} accounts")
        if len(accounts) <= 5:  # Show details for small groups
            for acc in accounts[:5]:
                print(f"   {acc}: {original_accounts[acc]['name']}")
        print()
    
    # Sample of converted accounts
    print("=== SAMPLE CONVERTED ACCOUNTS ===")
    sample_accounts = list(converted_accounts.items())[:10]
    for kontonr, data in sample_accounts:
        print(f"{kontonr}: {data['name']} ({data['type']}, VAT: {data['vat']}, Parent: {data['parent']})")
    
    print("\n=== CONVERSION SUMMARY ===")
    print(f"✅ Successfully converted {len(converted_accounts)} accounts")
    print(f"📊 Unique VAT mappings: {len(vat_mappings)}")
    print(f"📊 Unique type mappings: {len(type_mappings)}")
    
    if missing_in_converted:
        print(f"⚠️  {len(missing_in_converted)} accounts need manual review")
    else:
        print("✅ All accounts converted successfully")

def main():
    if len(sys.argv) != 3:
        print("Usage: python compare_kontoplaner.py <original_file> <converted_file>")
        print("Example: python compare_kontoplaner.py 'KONTOPLANER - EC-SEL.csv' 'converted_kontoplan.csv'")
        sys.exit(1)
    
    original_file = sys.argv[1]
    converted_file = sys.argv[2]
    
    compare_kontoplaner(original_file, converted_file)

if __name__ == "__main__":
    main()
