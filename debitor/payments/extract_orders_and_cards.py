#!/usr/bin/env python3
"""
Script to extract Order IDs and Card Types from lane3000-haveland.log
and save them to a CSV file.
"""

import re
import csv
import json
from datetime import datetime

def extract_orders_and_cards(log_file_path, output_csv_path):
    """
    Extract Order IDs and Card Types from the log file and save to CSV.
    
    Args:
        log_file_path (str): Path to the input log file
        output_csv_path (str): Path to the output CSV file
    """
    
    orders_data = {}  # Dictionary to store order_id -> card_type mapping
    
    # Regular expressions for pattern matching
    order_pattern = r'\[Order:\s*(\d+)\]'
    card_type_json_pattern = r'"cardType":"([^"]+)"'
    card_type_success_pattern = r'Payment successful - Card type:\s*([^,]+)'
    
    print(f"Processing log file: {log_file_path}")
    
    try:
        with open(log_file_path, 'r', encoding='utf-8') as file:
            for line_num, line in enumerate(file, 1):
                # Extract Order ID
                order_match = re.search(order_pattern, line)
                if order_match:
                    order_id = order_match.group(1)
                    
                    # Look for card type in JSON data
                    card_type_json_match = re.search(card_type_json_pattern, line)
                    if card_type_json_match:
                        card_type = card_type_json_match.group(1)
                        orders_data[order_id] = card_type
                        print(f"Line {line_num}: Order {order_id} -> Card Type: {card_type}")
                    
                    # Look for card type in success message
                    card_type_success_match = re.search(card_type_success_pattern, line)
                    if card_type_success_match:
                        card_type = card_type_success_match.group(1).strip()
                        orders_data[order_id] = card_type
                        print(f"Line {line_num}: Order {order_id} -> Card Type: {card_type}")
    
    except FileNotFoundError:
        print(f"Error: Log file '{log_file_path}' not found.")
        return
    except Exception as e:
        print(f"Error reading log file: {e}")
        return
    
    # Write results to CSV
    print(f"\nWriting results to CSV: {output_csv_path}")
    
    try:
        with open(output_csv_path, 'w', newline='', encoding='utf-8') as csvfile:
            writer = csv.writer(csvfile)
            
            # Write header
            writer.writerow(['Order_ID', 'Card_Type'])
            
            # Write data rows
            for order_id, card_type in orders_data.items():
                writer.writerow([order_id, card_type])
        
        print(f"Successfully extracted {len(orders_data)} orders with card types.")
        print(f"Results saved to: {output_csv_path}")
        
        # Display summary
        if orders_data:
            print("\nSummary:")
            card_types = {}
            for card_type in orders_data.values():
                card_types[card_type] = card_types.get(card_type, 0) + 1
            
            for card_type, count in sorted(card_types.items()):
                print(f"  {card_type}: {count} transactions")
        
    except Exception as e:
        print(f"Error writing CSV file: {e}")

def main():
    """Main function to run the extraction."""
    
    # File paths
    log_file = "/var/www/html/pblm/debitor/payments/lane3000-haveland.log"
    output_csv = "/var/www/html/pblm/debitor/payments/orders_and_cards.csv"
    
    print("Order ID and Card Type Extractor")
    print("=" * 40)
    
    # Run the extraction
    extract_orders_and_cards(log_file, output_csv)

if __name__ == "__main__":
    main()
