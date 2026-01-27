#!/usr/bin/env python3
"""
Kontoplan Converter - Final Version
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
            # Sales accounts (1000-1999)
            '1010': '1010',   # Sales with VAT
            '1011': '1011',   # Sales with VAT (manual)
            '1020': '1020',   # Export sales
            '1021': '1021',   # Export services
            '1025': '1025',   # EU sales
            '1026': '1026',   # EU services
            '1030': '1030',   # Sales without VAT
            '1049': '1049',   # Revenue accruals
            '1050': '1050',   # Work in progress
            '1068': '1068',   # Livestock changes
            
            # Cost accounts (2000-2999)
            '1302': '1302',   # Livestock write-down primo
            '1303': '1303',   # Livestock write-down ultimo
            '1305': '1305',   # External work with VAT
            '1306': '1306',   # Direct costs EU services
            '1307': '1307',   # Direct costs non-EU services
            '1308': '1308',   # External work without VAT
            '1310': '2010',   # Direct costs with VAT
            '1312': '1312',   # Direct costs without VAT
            '1315': '1015',   # Freight with VAT
            '1316': '1316',   # Freight without VAT
            '1317': '1317',   # Freight EU
            '1318': '1318',   # Freight non-EU
            '1320': '1320',   # Direct costs EU goods
            '1321': '1321',   # Direct costs EU services
            '1324': '1324',   # Direct costs non-EU goods
            '1325': '1325',   # Direct costs non-EU services
            '1326': '1326',   # Project salaries
            '1327': '1327',   # Project costs
            '1329': '1329',   # Direct costs without VAT
            '1330': '2010',   # Material consumption
            '1331': '1331',   # Customs etc.
            
            # Personnel costs (7000-7999)
            '2210': '7010',   # Salaries
            '2211': '2211',   # Holiday pay & SH
            '2212': '2212',   # Sick pay etc.
            '2213': '2213',   # B-honorarium
            '2214': '2214',   # Employee benefits
            '2215': '7165',   # Employer pension
            '2219': '2219',   # Salary clearing account
            '2222': '2222',   # AER (Combined payment)
            '2223': '7055',   # ATP
            '2224': '2224',   # DA-maternity
            '2230': '2230',   # KM-money
            '2241': '2241',   # Personnel expenses
            '2242': '2242',   # Project salaries
            '2250': '2250',   # Catering courses
            '2251': '2251',   # Personnel events
            '2252': '2252',   # Other catering
            '2253': '2253',   # Work clothes
            '2254': '2254',   # Gifts to personnel
            '2255': '2255',   # Courses with VAT
            '2256': '2256',   # Courses without VAT
            '2280': '2280',   # Holiday pay obligation
            '2290': '2290',   # Salary refunds
            
            # Operating expenses (4000-6999)
            '2750': '2750',   # Restaurant visits
            '2751': '2751',   # Business meetings
            '2753': '2753',   # Catering abroad
            '2754': '2754',   # Gifts and flowers
            '2760': '2760',   # Restaurant travel
            '2761': '2761',   # Other catering travel
            '2762': '2762',   # Travel allowances
            '2769': '2769',   # Catering travel abroad
            '2770': '2770',   # Travel expenses
            '2771': '2771',   # Hotel Denmark
            '2772': '2772',   # Hotel Finland
            '2773': '2773',   # Hotel abroad
            '2775': '2775',   # Transport Finland
            '2776': '2776',   # Transport abroad
            '2800': '2800',   # Advertising
            '2801': '2801',   # Internet ads EU
            '2802': '2802',   # Internet ads abroad
            '2803': '2803',   # Freight with VAT
            '2804': '2804',   # Freight without VAT
            '2805': '2805',   # Freight EU
            '2806': '2806',   # Freight non-EU
            '2850': '2850',   # Travel allowance
            '3100': '3100',   # Car operation
            '3110': '3110',   # Fuel
            '3120': '3120',   # Car insurance
            '3130': '3130',   # Vehicle tax
            '3131': '3131',   # Bridge and ferry fees
            '3140': '3140',   # Repairs/maintenance
            '3141': '3141',   # Other operating costs
            '3142': '3142',   # Fuel colored plates
            '3143': '3143',   # Car insurance colored
            '3144': '3144',   # Vehicle tax colored
            '3145': '3145',   # Repairs colored plates
            '3146': '3146',   # Bridge and ferry fees
            '3148': '3148',   # Other costs with VAT
            '3149': '3149',   # Other costs without VAT
            '3150': '3150',   # Deduction business travel
            '3160': '3160',   # Parking
            '3161': '3161',   # Parking with VAT
            '3162': '3162',   # Bridge fees with VAT
            '3410': '4010',   # Rent with VAT
            '3411': '3411',   # Rent without VAT
            '3420': '4030',   # Utilities
            '3421': '3421',   # Refunded water tax
            '3422': '3422',   # Refunded electricity tax
            '3430': '3430',   # Maintenance and cleaning
            '3431': '3431',   # Local costs without VAT
            '3435': '3435',   # Repairs and maintenance
            '3450': '3450',   # Insurance (local)
            '3600': '6010',   # Office supplies
            '3601': '3601',   # Newspaper subscription
            '3602': '3602',   # Office supplies without VAT
            '3604': '3604',   # IT expenses/software
            '3605': '3605',   # Small purchases IT
            '3606': '3606',   # IT expenses EU
            '3607': '3607',   # IT expenses non-EU
            '3608': '3608',   # IT expenses without VAT
            '3610': '3610',   # Repairs equipment
            '3617': '3617',   # Minor purchases
            '3618': '3618',   # Minor purchases reverse charge
            '3620': '6020',   # Telephone
            '3621': '3621',   # Internet connection
            '3623': '3623',   # Telephone without VAT
            '3626': '3626',   # Bank fees
            '3627': '3627',   # Public fees with deduction
            '3628': '3628',   # Postage and fees
            '3629': '3629',   # Tax fees without deduction
            '3630': '3630',   # Collection debtors
            '3631': '3631',   # Deposit fees
            '3640': '6130',   # Auditor/accounting
            '3641': '3641',   # Adjustment auditor
            '3645': '3645',   # Lawyer
            '3646': '3646',   # Other consulting
            '3650': '6085',   # Insurance
            '3651': '3651',   # Other insurance
            '3652': '3652',   # Other insurance
            '3659': '3659',   # Membership fees
            '3660': '3660',   # Professional literature with VAT
            '3661': '3661',   # Professional literature without VAT
            '3662': '3662',   # Membership fees with VAT
            '3663': '3663',   # Membership fees without VAT
            '3664': '3664',   # Web hosting and domains
            '3670': '3670',   # Lease/leasing equipment
            '3720': '3720',   # Loss on debtors
            '3725': '3725',   # Adjustment provision losses
            '3768': '3768',   # Other external costs with VAT
            '3769': '3769',   # Other external costs without VAT
            '3770': '3770',   # Cash differences
            '3780': '3780',   # Non-deductible costs
            '3781': '3781',   # Formation costs with VAT
            '3782': '3782',   # Formation costs without VAT
            '3910': '3910',   # Depreciation premises
            '3940': '3940',   # Depreciation equipment
            '3950': '3950',   # Depreciation IT
            
            # Financial accounts (9000-9999)
            '4310': '9070',   # Bank interest income
            '4311': '4311',   # Dividends Danish subsidiaries
            '4312': '4312',   # Income capital shares Danish subsidiaries
            '4313': '4313',   # Dividends Danish associates
            '4314': '4314',   # Income capital shares Danish associates
            '4315': '4315',   # Interest income subsidiaries
            '4316': '4316',   # Interest income subsidiaries
            '4317': '4317',   # Interest income subsidiaries
            '4318': '4318',   # Interest income associates
            '4319': '4319',   # Interest income associates
            '4330': '4330',   # Danish dividend without tax
            '4331': '4331',   # Danish dividend with 15.4% tax
            '4332': '4332',   # Danish dividend with 22% tax
            '4333': '4333',   # Danish dividend with 25% tax
            '4334': '4334',   # Danish dividend with 24% tax
            '4335': '4335',   # Foreign dividend without tax
            '4336': '4336',   # Foreign dividend with 15% tax
            '4337': '4337',   # Foreign dividend with tax
            '4338': '4338',   # Distributions investment companies
            '4339': '4339',   # Danish dividend tax
            '4340': '4340',   # Calculated relief foreign dividend
            '4341': '4341',   # Currency gains shares
            '4342': '4342',   # Unrealized currency gains shares
            '4343': '4343',   # Counter-post dividend tax
            '4360': '4360',   # Interest income debtors
            '4365': '4365',   # Collection fees debtors
            '4370': '4370',   # Interest allowance corporate tax
            '4380': '4380',   # Currency difference debtors gain
            '4381': '4381',   # Currency difference creditors gain
            '4410': '9250',   # Interest expense bank
            '4411': '4411',   # Interest expense bank2
            '4415': '4415',   # Interest costs subsidiaries
            '4416': '4416',   # Interest costs subsidiaries
            '4417': '4417',   # Interest costs subsidiaries
            '4418': '4418',   # Interest costs associates
            '4419': '4419',   # Interest costs associates
            '4420': '4420',   # Interest costs intercompany owners
            '4421': '4421',   # Interest costs intercompany owners
            '4430': '4430',   # Guarantee premium/provision
            '4450': '4450',   # Loan costs
            '4460': '4460',   # Interest expense creditors
            '4461': '4461',   # Collection fees
            '4465': '4465',   # Interest expense Tax & Customs
            '4470': '4470',   # Financing surcharge corporate tax
            '4475': '4475',   # Interest costs SKAT without deduction
            '4476': '4476',   # Interest costs SKAT with deduction
            '4477': '4477',   # Other interest costs
            '4480': '4480',   # Currency difference debtors loss
            '4481': '4481',   # Currency difference creditors loss
            '4610': '4610',   # Extraordinary income with VAT
            '4620': '4620',   # Extraordinary income without VAT
            '4630': '4630',   # Extraordinary expenses with VAT
            '4640': '4640',   # Extraordinary expenses without VAT
            '4670': '4670',   # Gain on sale fixed assets
            '4671': '4671',   # Loss on sale fixed assets
            '4810': '4810',   # Corporate tax current
            '4815': '4815',   # Tax credit R&D
            '4820': '4820',   # Adjustment deferred tax
            '4899': '4899',   # RESULT AFTER TAX
            '4940': '4940',   # RESULT DISPOSITION
            '4950': '4950',   # Net revaluation inner value method
            '4951': '4951',   # Net revaluation investment assets
            '4952': '4952',   # Premium on issue
            '4953': '4953',   # Year's allocation to other legal reserves
            '4954': '4954',   # Year's allocation to statutory reserves
            '4955': '4955',   # Year's allocation to other reserves
            '4956': '4956',   # Year's allocation to startup company reserve
            '4957': '4957',   # Transferred to security fund
            '4958': '4958',   # Transferred to reserve fund
            '4959': '4959',   # Transferred to disposition fund
            '4960': '4960',   # Year's result regarding bound fund capital
            '4961': '4961',   # Year's result regarding disposable fund capital
            '4962': '4962',   # Allocated from fund capital for later distribution
            '4963': '4963',   # Extraordinary dividend for accounting year
            '4964': '4964',   # Dividend for accounting year
            '4965': '4965',   # Transferred from year's result
            '4966': '4966',   # Minority interests share of year's result
            
            # Asset accounts (10000-19999)
            '5000': '5000',   # ASSETS
            '5001': '10305',  # Goodwill primo
            '5002': '5002',   # Goodwill additions
            '5003': '5003',   # Goodwill disposals
            '5004': '5004',   # Goodwill depreciation
            '5021': '5021',   # Intangible assets primo
            '5022': '5022',   # Intangible additions
            '5023': '5023',   # Intangible disposals
            '5024': '5024',   # Intangible depreciation
            '5091': '5091',   # Intangible assets primo
            '5092': '5092',   # Intangible additions
            '5094': '5094',   # Intangible disposals
            '5097': '5097',   # Intangible depreciation
            '5101': '11110',  # Land and buildings acquisition primo
            '5102': '5102',   # Land and buildings additions
            '5103': '5103',   # Land and buildings disposals
            '5106': '5106',   # Land and buildings depreciation primo
            '5107': '5107',   # Land and buildings depreciation
            '5111': '5111',   # Equipment acquisition primo
            '5112': '5112',   # Equipment additions
            '5113': '5113',   # Equipment disposals
            '5115': '5115',   # Equipment depreciation primo
            '5116': '5116',   # Equipment depreciation additions
            '5117': '5117',   # Equipment depreciation
            '5221': '11505',  # Equipment acquisition primo
            '5222': '5222',   # Equipment additions
            '5223': '5223',   # Equipment disposals
            '5226': '5226',   # Equipment depreciation primo
            '5227': '5227',   # Equipment depreciation
            '5231': '11610',  # IT equipment acquisition primo
            '5232': '5232',   # IT equipment additions
            '5233': '5233',   # IT equipment disposals
            '5236': '5236',   # IT equipment depreciation primo
            '5237': '5237',   # IT equipment depreciation
            '5312': '5312',   # Rent deposit with VAT
            '5313': '5313',   # Rent deposit without VAT
            '5322': '5322',   # Leasing deposit
            '5400': '5400',   # Capital shares subsidiaries
            '5401': '5401',   # Capital shares subsidiaries 2
            '5402': '5402',   # Capital shares subsidiaries 3
            '5410': '5410',   # Capital shares associates
            '5411': '5411',   # Capital shares associates 2
            '5420': '5420',   # Receivables subsidiaries
            '5421': '5421',   # Receivables subsidiaries 2
            '5429': '5429',   # Receivables subsidiaries tax contribution
            '5430': '5430',   # Receivables associates
            '5431': '5431',   # Receivables associates 2
            '5440': '5440',   # Shares
            '5441': '5441',   # Investment fund certificates
            '5442': '5442',   # Bonds
            '5512': '5512',   # Grain inventory
            '5514': '5514',   # Livestock
            '5515': '5515',   # Livestock write-downs
            '5516': '5516',   # Oil and gas inventory
            '5518': '5518',   # Fertilizer and chemical inventory
            '5520': '5520',   # Goods inventory
            '5600': '16110',  # Debtors
            '5605': '5605',   # Provision losses debtors
            '5610': '5610',   # Deferred tax asset
            '5611': '5611',   # Corporate tax receivable
            '5612': '5612',   # Corporate tax receivable long
            '5613': '5613',   # Corporate tax receivable tax credit R&D
            '5645': '5645',   # VAT receivable
            '5650': '5650',   # Other receivables
            '5651': '5651',   # Receivables owners and management
            '5654': '5654',   # Work in progress activities
            '5655': '5655',   # Work in progress costs
            '5660': '5660',   # Prepaid items
            '5665': '5665',   # Employee receivables
            '5810': '18010',  # Cash
            '5820': '18025',  # Bank account
            '5821': '5821',   # Bank account 2
            '5825': '5825',   # Bank securities depot 1
            '5826': '5826',   # Bank securities depot 2
            '5990': '5990',   # ASSETS TOTAL
            
            # Equity and liability accounts (20000-29999)
            '6100': '6100',   # EQUITY
            '6110': '20010',  # Share capital
            '6120': '6120',   # Premium fund
            '6125': '6125',   # Reserve net revaluation inner value
            '6128': '6128',   # Reserve development costs
            '6130': '20810',  # Retained earnings previous years
            '6135': '6135',   # Dividend
            '6140': '6140',   # Period result after tax
            '6199': '6199',   # EQUITY TOTAL
            '6300': '6300',   # PROVISIONS
            '6310': '6310',   # Deferred tax
            '6311': '6311',   # Provisions capital shares subsidiaries
            '6312': '6312',   # Provisions capital shares associates
            '6320': '6320',   # Other provisions
            '6399': '6399',   # PROVISIONS TOTAL
            '6600': '6600',   # DEBT OBLIGATIONS
            '6609': '6609',   # Corporate tax long-term
            '6610': '26830',  # Corporate tax payable
            '6611': '6611',   # Tax account
            '6750': '6750',   # Overdraft
            '6800': '26010',  # Creditors
            '6830': '6830',   # Accrued expenses
            '6831': '6831',   # Auditor payable
            '6835': '6835',   # Employee expenses payable
            '6840': '6840',   # Debt subsidiaries long
            '6842': '6842',   # Debt associates long
            '6844': '6844',   # Debt subsidiaries
            '6845': '6845',   # Debt subsidiaries 2
            '6846': '6846',   # Debt subsidiaries 3
            '6850': '6850',   # Debt associates
            '6851': '6851',   # Debt associates 2
            '6860': '6860',   # Intercompany shareholders
            '6861': '6861',   # Intercompany shareholders 2
            '6900': '27110',  # VAT and duties
            '6901': '6901',   # VAT payable primo
            '6902': '6902',   # Output VAT
            '6903': '6903',   # Input VAT
            '6906': '6906',   # Acquisition VAT
            '6907': '6907',   # Acquisition VAT counter-post
            '6910': '6910',   # Oil tax
            '6911': '6911',   # Electricity tax
            '6912': '6912',   # Gas tax
            '6914': '6914',   # CO2 tax
            '6915': '6915',   # Water tax
            '6916': '6916',   # Transferred to VAT receivable
            '6917': '6917',   # VAT paid
            '6920': '6920',   # A-tax payable
            '6921': '6921',   # ATP payable
            '6922': '6922',   # Pension payable
            '6923': '6923',   # Holiday pay & SH payable
            '6924': '6924',   # DA-maternity payable
            '6925': '6925',   # Social contributions payable
            '6930': '6930',   # AM-contribution payable
            '6940': '6940',   # Salaries payable
            '6945': '6945',   # Other salary items payable
            '6946': '6946',   # Provision holiday pay obligation
            '6947': '6947',   # Other debt
            '6948': '6948',   # Clearing account bank
            '6949': '6949',   # Clearing account depot/bank
            '6950': '6950',   # Clearing account tax account
            '7999': '7999',   # DEBT OBLIGATIONS TOTAL
            '8999': '8999',   # LIABILITIES TOTAL
            '9900': '9900',   # Analysis/error account
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
