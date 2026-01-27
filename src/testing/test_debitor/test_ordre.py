import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))

import time 

import pytest

from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support.ui import Select
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
def test_portovare():
    driver = test_context.get_driver()
    wait = WebDriverWait(driver, 20)

    reset(driver, wait)

    # Set protovare
    ## Unfold system
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="system"]')))
    system_link.click()

    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Indstillinger')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    ## Open diverse, ordrevalg
    wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='diverse.php?valg=diverse']/button"))).click()
    wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='diverse.php?sektion=ordre_valg']/button"))).click()

    ## Set the portovare to be "proto"
    cvr = wait.until(EC.presence_of_element_located((By.NAME, "portovarenr")))
    cvr.clear()  # Clear any pre-filled text
    cvr.send_keys("porto")

    wait.until(EC.element_to_be_clickable((By.NAME, "submit"))).click()

    ## Validate portovarenr
    input_element = wait.until(EC.presence_of_element_located((By.NAME, "portovarenr")))
    input_value = input_element.get_attribute("value")
    assert input_value == "porto", f"Unexpected value portovarenr: {input_value}, should be 'porto'"

    # Opret portovare
    ## Open Lager
    Config.toMain(driver)
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="lager"]')))
    system_link.click()

    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Varer')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    ## Opret vare
    button = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='varekort.php?returside=varer.php']")))
    button.click()

    ## Insert varenr "porto"
    wait.until(EC.presence_of_element_located((By.NAME, "varenr"))).send_keys("porto")
    wait.until(EC.element_to_be_clickable((By.NAME, "saveItem"))).click()

    ## Insert description
    wait.until(EC.presence_of_element_located((By.NAME, "beskrivelse0"))).send_keys("Fragt poto")

    ## Insert salgspris
    salgspris = wait.until(EC.presence_of_element_located((By.NAME, "salgspris")))
    salgspris.clear()
    salgspris.send_keys("50")

    ## Insert varegruppe
    dropdown = wait.until(EC.presence_of_element_located((By.NAME, "ny_gruppe")))
    select = Select(dropdown)
    select.select_by_value("4")

    ## Save
    wait.until(EC.element_to_be_clickable((By.NAME, "saveItem"))).click()

    # Open ordre
    Config.toMain(driver)
    system_link = wait.until(EC.element_to_be_clickable((By.XPATH, '//*[@id="debitor"]')))
    system_link.click()

    indstillinger_link = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(text(), 'Ordre')]")))
    indstillinger_link.click()

    Config.toFrame(driver)

    # Opret ordre
    button = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[@href='ordre.php?konto_id=&returside=ordreliste.php?konto_id=']")))
    button.click()

    ## Input non exsisting kontonr
    wait.until(EC.presence_of_element_located((By.NAME, "kontonr"))).send_keys("1000")
    wait.until(EC.element_to_be_clickable((By.XPATH, "//input[@name='b_submit'][@value='Gem']"))).click()

    ## Fill out kontonr information
    wait.until(EC.presence_of_element_located((By.NAME, "firmanavn"))).send_keys("Saldi.dk ApS")

    ## Click opret
    opret_button = wait.until(EC.element_to_be_clickable((By.XPATH, "//input[@type='submit'][@name='create_debtor'][@value='Opret']")))
    opret_button.click()
    
    ## Validate portovarenr in ordre
    input_element = wait.until(EC.presence_of_element_located((By.NAME, "vare1")))
    input_value = input_element.get_attribute("value")
    assert input_value == "porto", f"Unexpected value varenr: {input_value}, should be 'porto'"

    # Opret ordre
    ny_button = wait.until(EC.element_to_be_clickable((By.XPATH, "//a[contains(@href, 'javascript:confirmClose')]//button[text()='Ny']")))
    ny_button.click()

    ## Input non exsisting kontonr
    wait.until(EC.presence_of_element_located((By.NAME, "kontonr"))).send_keys("1000")
    wait.until(EC.element_to_be_clickable((By.XPATH, "//input[@name='b_submit'][@value='Gem']"))).click()

    ## Validate portovarenr in ordre
    input_element = wait.until(EC.presence_of_element_located((By.NAME, "vare1")))
    input_value = input_element.get_attribute("value")
    assert input_value == "porto", f"Unexpected value varenr: {input_value}, should be 'porto'"

if __name__ == "__main__":
    pytest.main([__file__])