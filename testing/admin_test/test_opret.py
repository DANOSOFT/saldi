import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))

import pytest

from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.alert import Alert

from modules.config import Config

@pytest.mark.order(1)
def test_click_opret_regnskab():
    # Setup the webdriver
    driver = Config.setup_webdriver()

    try:
        # Perform login
        Config.login(driver)

        ## Wait for the "Opret regnskab" link to be clickable
        wait = WebDriverWait(driver, 10)
        opret_link = wait.until(
            EC.element_to_be_clickable((By.LINK_TEXT, "Opret regnskab"))
        )
        
        ## Click the link
        opret_link.click()

        # Enter information for the create regnskab process
        ## Wait for and fill the 'regnskab' field
        regnskab_field = wait.until(EC.presence_of_element_located((By.NAME, "regnskab")))
        regnskab_field.clear()  # Clear any pre-filled text (optional)
        regnskab_field.send_keys(Config.testSystemName)

        ## Wait for and fill the 'brugernavn' field
        username_field = wait.until(EC.presence_of_element_located((By.NAME, "brugernavn")))
        username_field.clear()  # Clear any pre-filled text (optional)
        username_field.send_keys(Config.testSystemAdminName)

        ## Input passwod
        wait.until(EC.presence_of_element_located((By.NAME, "passwd"))) .send_keys(Config.testSystemPassword)
        wait.until(EC.presence_of_element_located((By.NAME, "passwd2"))).send_keys(Config.testSystemPassword)

        submit_button = wait.until(
            EC.element_to_be_clickable((By.XPATH, "//input[@name='submit']"))
        )
        submit_button.click()

        # Wait for the alert to be present
        alert = wait.until(EC.alert_is_present())

        ## Get the alert text
        alert_text = alert.text

        ## Perform an assertion to check the alert text
        assert "findes allerede" not in alert_text, "Regnskab testing already exsists, delte it from the system and try agein"

        ## Dismiss the alert
        alert.dismiss()
        print("Alert dismissed.")

        # Logout
        logout_link = wait.until(
            EC.element_to_be_clickable((By.LINK_TEXT, "Log ud"))
        )
        logout_link.click()
    finally:
        # Cleanup: Close the browser
        driver.quit()


if __name__ == "__main__":
    test_click_opret_regnskab()