# Initial PokeBase Task Board

# User Stories

## Epic 1: User accounts
1.	As a new user, I want to create an account so that I can use the services of Pokebase and interact with other users. <br>
	Acceptance Criteria: 
*	A page exists for me to enter credentials to login to the site.
*	I can enter my username and password correctly and only then be logged in.
*	I can log out of my account to enable logging into other accounts.
2.	As a registered user, I want to log in and out in a secure way, so my information is protected. <br>
	Acceptance Criteria:
*	My username and password are stored securely using proper login best practices.
*	My password is hashed with salt and pepper when stored in the database. 
*	Hashing algorithm is slow enough to prevent brute force attacks
3.	As a user, I only want to be able to access what I am allowed to based on my role <br>
	Acceptance Criteria:
*	A user should be able to be classified as one of “Manager”, “Employee”, and “Customer”
*	A user who is not a Manager or Employee (not logged in) cannot make edit request to the API. 
*	A user who is not a Manager cannot make delete requests to the API.  
4.	As a manager of a store, I want to be able to set access levels to other accounts to make sure someone can’t just take the cards for free. <br>
	Acceptance Criteria: 
*	A page exists to view all user accounts created. 
*	I can edit other user’s role.
*	Nonmanagers cannot access or see this page or links to this page.

## Epic 2: Inventory System
5.	As a user, I want to be able to add and remove specific cards in my inventory. <br>
	Acceptance Criteria:
*	I can scan a card after a trade or sale is made, and it will populate the required fields for the card, then add the card to my inventory. 
*	When a sale or trade is made in relation to a specific card the count of that card will be reduced.
*	If a cards count reaches 0, then it will not show up in the inventory and will return once a card is bought or traded(collected) back of the same type.
6.	As a user, I want to be able to search through the cards that are in my inventory. <br>
	Acceptance Criteria:
*	I want to search for cards in my inventory via the different defining features.
*	If there are more than one version of the same card, the system clearly shows this.
*	I can click on each card to see more detailed information.
7.	As a user, I want to be able to view detailed information about a card so that I can verify its value and characteristics. <br>
	Acceptance Criteria:
*	When in the main search menu, I can see the cards image and cards specific features such as card number and set number.
*	When I click on any given card, I am shown all the information that we have stored in the database for that specific card based on the card number.
*	Other similar cards may be shown as well if there are more than one.
8.	As a manager, I want to be able to update the condition of a card so that my inventory accurately reflects its quality. <br>
	Acceptance Criteria: 
*	An “Update Card” option appears next to a card that I can click on.
*	I can view and edit a card’s info, including condition and grade, etc.
*	Any card modification submitted is tracked into audit logs.
9.	As a manager, I want to view inventory change history so that I can track who modified inventory records. <br>
	Acceptance Criteria: 
*	I can view an audit logs page that shows displays information from a table tracking inventory changes. 
*	Nonmanagers should not be able to access this page. 

## Epic 3: POS System
10.	As an employee, I want to purchase/sell cards from/to customers so that inventory can be replenished. <br>
	Acceptance Criteria: 
*	A page exists for me to create a new transaction. 
*	This page allows adding card(s) to give, card(s) to receive, and money being given/received in the transaction. 
11.	As a manager, I want all complete transactions recorded so that sales history can be reviewed.
	Acceptance Criteria: 
*	
12.	As a cashier/employee, I want inventory quantities automatically updated after a completed sale so that stock levels remain accurate.

## Epic 4: Analytics
13.	As a manager, I want to view daily reports to monitor store performance. <br>
	Acceptance Criteria: 
*	A page exists to show daily store performance. 
*	This page is not accessible to nonManager users.
*	The page displays a chart of sales historical data passed on search parameters, indicating profit or loss for the day.
14.	As a manager, I want to be able to see the total inventory value so that I can understand my store’s assets. <br>
	Acceptance Criteria: 
*	A page exists to show the inventory’s current total value as well as value on prior selectable days in history.
*	This page displays a chart of total inventory value over history.
*	This page has search parameters to help navigate and filter through historical data.
15.	As a manager, I want to search past transactions so that I can investigate customer issues and inventory discrepancies. <br>
	Acceptance Criteria: 
*	A page exists to look up past transactions regarding a specific card.
*	Search queries allow me to enter card name, set, set number, rarity, condition, and language.
*	Results are displayed in a table showing stored infromation on past transactions and prices.
*	Nonmanagers cannot access this page or functionality.

## Epic 5: Scanner (Stretch)
16.	As an employee, I want to the ability to calculate data on a card from a picture. <br>
	Acceptance Criteria: 
*	A computer vision model is able to take card pictures as input and output predicted information such as card name, set, set number, language, condition, and print. 
*	A python API server handles requests for image processing and data extraction.
*	I can access endpoints by sending the image info and retrieving information back about the card, or an error if no info or unrecognized.
17.	As a user, I want to be able to scan a card to retrieve and display information on that card. <br>
	Acceptance Criteria:
*	A page exists that lets me open a camera and scan or take a picture of a card.
*	The page auto populates with information on this card after scanning completes, including card name, set, set number, language, condition, and print.
*	The page displays the data currently stored in the database.
*	I am able to query the TCG API with this information to retrieve the current price point with a button.
18.	As an employee, I want to be able to scan cards on checkout to rapidly add them to a transaction. <br>
	Acceptance Criteria:
*	A scan button is present on the new transaction page that lets me scan cards. 
*	Scanned cards can show up under either giving or receiving cards with relevant information. 
*	Card info is still editable on the transaction after scanning.
