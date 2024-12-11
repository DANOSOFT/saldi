from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException


class Config:
    baseurl = "https://dev.saldi.dk/mmk"
    # The name of the system that we will be testing in, will be created in admin_test/test_opret.py::test_click_opret_regnskab
    testSystemName = "testing"
    testSystemAdminName = "test"
    testSystemPassword = "test123"

    @staticmethod
    def setup_webdriver():
        driver = webdriver.Firefox()
        driver.get(Config.baseurl + "/index/index.php")
        return driver

    @staticmethod
    def login(driver, regnskab="develop", username="admin", password="05November2024"):
        wait = WebDriverWait(driver, 10)
        # Type login information and continue
        wait.until(EC.presence_of_element_located((By.NAME, "regnskab"))).send_keys(regnskab)
        wait.until(EC.presence_of_element_located((By.NAME, "brugernavn"))).send_keys(username)
        wait.until(EC.presence_of_element_located((By.NAME, "password"))).send_keys(password)
        login_button = wait.until(
            EC.element_to_be_clickable((By.XPATH, "//input[@value='Login']"))
        )
        login_button.click()

        # Check for the already logged in page
        try:
            # Wait for the page to load and check for the button
            wait = WebDriverWait(driver, 5)  # Wait up to 5 seconds
            continue_button = wait.until(
                EC.presence_of_element_located((By.XPATH, "//input[@name='fortsaet' and @value='Fortsæt']"))
            )
            # If the button is found, click it
            continue_button.click()
            print("Continue button clicked.")
        except TimeoutException:
            # If the button is not found within the timeout, continue
            print("Continue button not found, moving on.")

    @staticmethod
    def toggleSidebarMenu(driver, tabName):
        """
        Toggles on of the tabs in the sidebar based on the tab name
        """
        tabs = {
            "overblik": 1,
            "finans": 2,
            "debitor": 3,
            "kreditor": 4,
        }

        wait = WebDriverWait(driver, 10)

        system_link = wait.until(
            EC.element_to_be_clickable((By.XPATH, f"/html/body/div[2]/ul[1]/li[{tabNumber}]/div"))
        )
        system_link.click()

    @staticmethod
    def toFrame(driver):
        wait = WebDriverWait(driver, 10)

        iframe = wait.until(
            EC.presence_of_element_located((By.TAG_NAME, "iframe"))
        )
        driver.switch_to.frame(iframe)

        return iframe

    @staticmethod
    def toMain(driver):
        driver.switch_to.default_content()

if __name__ == "__main__":
    driver = Config.setup_webdriver()
    Config.login(driver)
