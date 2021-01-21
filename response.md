# Response
## API Document (required)
  Import [this](/postman/api.json) json file to Postman
  ###the extra api cleandb password is kdan. for clean all of db data.

## Import Data Commands (required)
  There are two apis to import data:
    import store : {base_url}/api/v1/store
    import user : {base_url}/api/v1/user
   You can simply use Api json in Postman 

## Data Schema
### book_store json data schema

	stores:
		id
		name
		created_at
		updated_at
	store_balances:
		id
		store_id
		balance
		created_at
		updated_at
	store_openning_time:
		id
		store_id
		day
		start_time
		end_time
		created_at
		updated_at
	store_books:
		id
		store_id:
		name:
		balance
		created_at
		updated_at

### user json data schema

  	customers
		**id**
		name
		created_at
		updated_at
	customer_balance:
		**id**
		customer_id (foreign key)
		balance
		created_at
		updated_at
	purchase_records:
    **id**
		customer_id (foreign key)
		store_id (foreign key)
		customer_book_id (foreign key)
		amount
		transactionDate
		created_at
		updated_at
	customer_book
		**id**
		customer_id (foreign key)
		store_id (foreign key)
		name
		created_at
		updated_at
## Test Coverage Report(optional)
  No support
  
## Demo Site Url (optional)
  [Click Me](https://ryansbookstorm.herokuapp.com/)
