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

@pytest.mark.order(2)
def test_stamdata():
    driver = test_context.get_driver()
    wait = WebDriverWait(driver, 10)

    reset(driver, wait)

    # Open Stamdata
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="system"]')))
    system_link.click()

    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Indstillinger')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    stamdata_button = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='stamkort.php']/button")))
    stamdata_button.click()

    cvr = wait.until(EC.presence_of_element_located((By.NAME, "cvrnr")))
    cvr.clear()  # Clear any pre-filled text (optional)
    cvr.send_keys("/20756438/")
    time.sleep(2)

    submit_button = wait.until(EC.element_to_be_clickable((By.NAME, "submit")))
    submit_button.click()

    input_element = wait.until(EC.presence_of_element_located((By.NAME, "firmanavn")))
    input_value = input_element.get_attribute("value")
    assert input_value == "Saldi.dk ApS", f"Unexpected value: {input_value}"


if __name__ == "__main__":
    pytest.main([__file__])