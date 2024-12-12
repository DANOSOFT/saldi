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


def reset(driver, wait):
    # Open debitor
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="system"]')))
    system_link.click()

    # Open konti
    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Indstillinger')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='diverse.php?valg=diverse']/button"))).click()
    wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='diverse.php?sektion=kontoindstillinger']/button"))).click()

    wait.until(EC.element_to_be_clickable((By.NAME, "nulstil"))).click()

    ## Check there is not a fatal error
    alert = wait.until(EC.alert_is_present())
    alert_text = alert.text
    assert "Er du sikker på at du vil nulstille dit regnskab? Tag en sikkerhedskopi først!" == alert_text, f"Fejl, got alert {alert_text}"
    alert.accept()

    luk_button = wait.until(EC.element_to_be_clickable((By.XPATH, "//div[@id='boks1']//button[text()='Luk']")))
    luk_button.click()

    Config.toMain(driver)

    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="system"]')))
    system_link.click()
    dashboard_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="dashboard"]')))
    dashboard_link.click()

    time.sleep(2)
