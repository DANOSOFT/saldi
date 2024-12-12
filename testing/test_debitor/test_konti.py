import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))

import time 

import pytest

from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.alert import Alert

from modules.config import Config
from modules.nulstil import reset
from modules.testContext import TestContext

# Instantiate the shared test context
test_context = TestContext()

@pytest.fixture(scope="module", autouse=True)
def selenium_context():
    test_context.start()
    yield
    test_context.stop()

@pytest.mark.order(3)
def test_opret():
    # Get the driver
    driver = test_context.get_driver()
    wait = WebDriverWait(driver, 10)

    reset(driver, wait)

    # Open debitor
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="debitor"]')))
    system_link.click()

    # Open konti
    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Konti')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    # Create a new debitor
    ## Click new
    ny_button = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='debitorkort.php?returside=debitor.php']")))
    ny_button.click()

    ## Input cvr number for opslag
    wait.until(EC.presence_of_element_located((By.NAME, "cvrnr"))).send_keys("/20756438/")
    time.sleep(1)

    submit_button = wait.until(EC.element_to_be_clickable((By.NAME, "submit")))
    submit_button.click()

    ## Check there is not a fatal error
    alert = wait.until(EC.alert_is_present())
    alert_text = alert.text
    assert "uforudset hændelse" not in alert_text.lower(), "Fejl, tjek serverlog"
    alert.dismiss()

    ## Check the kontonr we have created
    input_element = wait.until(EC.presence_of_element_located((By.NAME, "ny_kontonr")))
    input_value = input_element.get_attribute("value")
    assert input_value == 1000, f"Unexpected value kontonr: {input_value}, should be 1000"

    # Create categories
    wait.until(EC.presence_of_element_located((By.NAME, "newCatName"))).send_keys(f"cat1")
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()
    wait.until(EC.presence_of_element_located((By.NAME, "newCatName"))).send_keys("cat2")
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()
    
    ## Assert values to ensure creating
    input_element = wait.until(EC.presence_of_element_located((By.XPATH, "/html/body/table/tbody/tr[2]/td/table/tbody/tr[3]/td/table/tbody/tr[1]/td[1]/table/tbody/tr[2]/td[1]")))
    input_value = input_element.text
    assert input_value == "cat1", f"Unexpected value: {input_value}, should be cat1, maybe debitor kategoriere already exsist? Try removing exsisting kategoriere"

    input_element = wait.until(EC.presence_of_element_located((By.XPATH, "/html/body/table/tbody/tr[2]/td/table/tbody/tr[3]/td/table/tbody/tr[1]/td[1]/table/tbody/tr[3]/td[1]")))
    input_value = input_element.text
    assert input_value == "cat2", f"Unexpected value: {input_value}, should be cat2, maybe debitor kategoriere already exsist? Try removing exsisting kategoriere"

    ## Enable the categories
    wait.until(EC.presence_of_element_located((By.NAME, "cat_valg[0]"))).click()
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()
    wait.until(EC.presence_of_element_located((By.NAME, "cat_valg[1]"))).click()
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()

    input_element = wait.until(EC.presence_of_element_located((By.NAME, "cat_valg[0]")))
    is_checked = input_element.get_attribute("checked")
    assert is_checked, f"Unexpected value cat1 checkbox: {input_value}, should be checked"

    input_element = wait.until(EC.presence_of_element_located((By.NAME, "cat_valg[1]")))
    is_checked = input_element.get_attribute("checked")
    assert is_checked, f"Unexpected value cat1 checkbox: {input_value}, should be checked"


    # Rename category 1
    wait.until(EC.presence_of_element_located((By.XPATH, '//*[@id="rename_category-0"]'))).click()
    alert = WebDriverWait(driver, 10).until(EC.alert_is_present())
    alert_text = alert.text
    assert alert_text == "Vil du omdøbe denne kategori?", f"Unexpected popup {alert_text}"
    alert.accept()
    time.sleep(0.5)

    ## Input the new cat name
    username_field = wait.until(EC.presence_of_element_located((By.NAME, "newCatName")))
    username_field.clear()
    username_field.send_keys("cat1 renamed")
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()

    ## Validate new cat name
    input_element = wait.until(EC.presence_of_element_located((By.XPATH, "/html/body/table/tbody/tr[2]/td/table/tbody/tr[3]/td/table/tbody/tr[1]/td[1]/table/tbody/tr[2]/td[1]")))
    input_value = input_element.text
    assert input_value == "cat1 renamed", f"Unexpected value: {input_value}, should be cat1 renamed"

    # Rename category 2
    wait.until(EC.presence_of_element_located((By.XPATH, '//*[@id="rename_category-1"]'))).click()
    alert = WebDriverWait(driver, 10).until(EC.alert_is_present())
    alert_text = alert.text
    assert alert_text == "Vil du omdøbe denne kategori?", f"Unexpected popup {alert_text}"
    alert.accept()
    time.sleep(0.5)

    ## Input the new cat name
    username_field = wait.until(EC.presence_of_element_located((By.NAME, "newCatName")))
    username_field.clear()
    username_field.send_keys("cat2 renamed")
    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()

    ## Validate new cat name
    input_element = wait.until(EC.presence_of_element_located((By.XPATH, "/html/body/table/tbody/tr[2]/td/table/tbody/tr[3]/td/table/tbody/tr[1]/td[1]/table/tbody/tr[3]/td[1]")))
    input_value = input_element.text
    assert input_value == "cat2 renamed", f"Unexpected value: {input_value}, should be cat2 renamed"
    
    # Remove category 1
    wait.until(EC.presence_of_element_located((By.XPATH, '//*[@id="delete_category-0"]'))).click()
    alert = WebDriverWait(driver, 10).until(EC.alert_is_present())
    alert_text = alert.text
    assert alert_text == "Vil du slette denne kategori?", f"Unexpected popup {alert_text}"
    alert.accept()

    # Remove category 2
    wait.until(EC.presence_of_element_located((By.XPATH, '//*[@id="delete_category-1"]'))).click()
    alert = WebDriverWait(driver, 10).until(EC.alert_is_present())
    alert_text = alert.text
    assert alert_text == "Vil du slette denne kategori?", f"Unexpected popup {alert_text}"
    alert.accept()


if __name__ == "__main__":
    pytest.main([__file__])