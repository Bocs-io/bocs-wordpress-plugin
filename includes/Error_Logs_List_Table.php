<?php

class Error_Logs_List_Table extends WP_List_Table {

    public function get_columns(){

        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'module'    => 'Module',
            'code'      => 'Error Code',
            'message'   => 'Error Message',
            'log_time'      => 'Date/Time'
        );

    }

    public function prepare_items(){

        $columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->_get_table_data();

    }

    public function column_default($item, $column_name)
	{
		switch ($column_name){
			case 'module':
			case 'code':
			case 'message':
			case 'log_time':
			default:
				return $item[$column_name];
		}

	}

    public function column_cb($item)
	{
		return sprintf(
			'<input type="checkbox" name="element[]" value="%s" />',
			$item['id']
		);
	}

    private function _get_table_data(){

        $data =  '[{"id":1,"module":"contacts","code":400,"message":"Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia.","log_time":"2023-06-13 11:21:14"},
{"id":2,"module":"orders","code":500,"message":"Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh. Quisque id justo sit amet sapien dignissim vestibulum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Nulla dapibus dolor vel est. Donec odio justo, sollicitudin ut, suscipit a, feugiat et, eros. Vestibulum ac est lacinia nisi venenatis tristique. Fusce congue, diam id ornare imperdiet, sapien urna pretium nisl, ut volutpat sapien arcu sed augue. Aliquam erat volutpat. In congue.","log_time":"2023-01-06 15:35:18"},
{"id":3,"module":"subscriptions","code":404,"message":"Quisque id justo sit amet sapien dignissim vestibulum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Nulla dapibus dolor vel est. Donec odio justo, sollicitudin ut, suscipit a, feugiat et, eros. Vestibulum ac est lacinia nisi venenatis tristique. Fusce congue, diam id ornare imperdiet, sapien urna pretium nisl, ut volutpat sapien arcu sed augue.","log_time":"2022-09-01 20:33:36"},
{"id":4,"module":"invoices","code":400,"message":"In quis justo. Maecenas rhoncus aliquam lacus. Morbi quis tortor id nulla ultrices aliquet.","log_time":"2023-01-30 22:13:15"},
{"id":5,"module":"subscriptions","code":404,"message":"Cras mi pede, malesuada in, imperdiet et, commodo vulputate, justo. In blandit ultrices enim. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Proin interdum mauris non ligula pellentesque ultrices. Phasellus id sapien in sapien iaculis congue. Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl.","log_time":"2023-03-01 13:50:56"},
{"id":6,"module":"subscriptions","code":200,"message":"Morbi non lectus. Aliquam sit amet diam in magna bibendum imperdiet.","log_time":"2022-09-02 03:14:22"},
{"id":7,"module":"contacts","code":502,"message":"Sed accumsan felis. Ut at dolor quis odio consequat varius. Integer ac leo. Pellentesque ultrices mattis odio.","log_time":"2023-04-18 01:26:19"},
{"id":8,"module":"orders","code":200,"message":"Morbi quis tortor id nulla ultrices aliquet. Maecenas leo odio, condimentum id, luctus nec, molestie sed, justo. Pellentesque viverra pede ac diam. Cras pellentesque volutpat dui. Maecenas tristique, est et tempus semper, est quam pharetra magna, ac consequat metus sapien ut nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris viverra diam vitae quam. Suspendisse potenti. Nullam porttitor lacus at turpis.","log_time":"2022-08-09 02:06:34"},
{"id":9,"module":"contacts","code":502,"message":"Suspendisse potenti. Cras in purus eu magna vulputate luctus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus vestibulum sagittis sapien.","log_time":"2022-10-16 04:48:04"},
{"id":10,"module":"invoices","code":401,"message":"In sagittis dui vel nisl. Duis ac nibh. Fusce lacus purus, aliquet at, feugiat non, pretium quis, lectus. Suspendisse potenti. In eleifend quam a odio. In hac habitasse platea dictumst. Maecenas ut massa quis augue luctus tincidunt.","log_time":"2023-07-05 23:41:16"},
{"id":11,"module":"products","code":200,"message":"Duis consequat dui nec nisi volutpat eleifend. Donec ut dolor. Morbi vel lectus in quam fringilla rhoncus. Mauris enim leo, rhoncus sed, vestibulum sit amet, cursus id, turpis. Integer aliquet, massa id lobortis convallis, tortor risus dapibus augue, vel accumsan tellus nisi eu orci. Mauris lacinia sapien quis libero. Nullam sit amet turpis elementum ligula vehicula consequat. Morbi a ipsum.","log_time":"2023-05-20 23:37:48"},
{"id":12,"module":"subscriptions","code":504,"message":"Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum. Mauris ullamcorper purus sit amet nulla. Quisque arcu libero, rutrum ac, lobortis vel, dapibus at, diam.","log_time":"2023-01-09 23:31:35"},
{"id":13,"module":"contacts","code":500,"message":"In hac habitasse platea dictumst. Morbi vestibulum, velit id pretium iaculis, diam erat fermentum justo, nec condimentum neque sapien placerat ante. Nulla justo. Aliquam quis turpis eget elit sodales scelerisque.","log_time":"2022-11-03 04:03:49"},
{"id":14,"module":"orders","code":500,"message":"Maecenas ut massa quis augue luctus tincidunt. Nulla mollis molestie lorem. Quisque ut erat. Curabitur gravida nisi at nibh.","log_time":"2023-06-10 06:46:42"},
{"id":15,"module":"subscriptions","code":401,"message":"Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl. Aenean lectus. Pellentesque eget nunc. Donec quis orci eget orci vehicula condimentum. Curabitur in libero ut massa volutpat convallis.","log_time":"2023-04-19 21:35:23"},
{"id":16,"module":"contacts","code":504,"message":"Suspendisse potenti.","log_time":"2023-07-22 01:06:46"},
{"id":17,"module":"invoices","code":404,"message":"Donec diam neque, vestibulum eget, vulputate ut, ultrices vel, augue. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec pharetra, magna vestibulum aliquet ultrices, erat tortor sollicitudin mi, sit amet lobortis sapien sapien non mi. Integer ac neque. Duis bibendum. Morbi non quam nec dui luctus rutrum. Nulla tellus. In sagittis dui vel nisl. Duis ac nibh. Fusce lacus purus, aliquet at, feugiat non, pretium quis, lectus. Suspendisse potenti.","log_time":"2022-09-24 08:04:15"},
{"id":18,"module":"subscriptions","code":200,"message":"Aliquam augue quam, sollicitudin vitae, consectetuer eget, rutrum at, lorem. Integer tincidunt ante vel ipsum. Praesent blandit lacinia erat. Vestibulum sed magna at nunc commodo placerat. Praesent blandit. Nam nulla. Integer pede justo, lacinia eget, tincidunt eget, tempus vel, pede. Morbi porttitor lorem id ligula. Suspendisse ornare consequat lectus. In est risus, auctor sed, tristique in, tempus sit amet, sem.","log_time":"2023-01-11 07:23:05"},
{"id":19,"module":"contacts","code":401,"message":"Donec dapibus. Duis at velit eu est congue elementum. In hac habitasse platea dictumst. Morbi vestibulum, velit id pretium iaculis, diam erat fermentum justo, nec condimentum neque sapien placerat ante. Nulla justo. Aliquam quis turpis eget elit sodales scelerisque.","log_time":"2023-06-30 02:09:52"},
{"id":20,"module":"products","code":401,"message":"Mauris sit amet eros. Suspendisse accumsan tortor quis turpis. Sed ante. Vivamus tortor. Duis mattis egestas metus. Aenean fermentum. Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh.","log_time":"2023-04-06 23:49:53"},
{"id":21,"module":"invoices","code":200,"message":"Fusce congue, diam id ornare imperdiet, sapien urna pretium nisl, ut volutpat sapien arcu sed augue. Aliquam erat volutpat. In congue. Etiam justo. Etiam pretium iaculis justo. In hac habitasse platea dictumst. Etiam faucibus cursus urna. Ut tellus. Nulla ut erat id mauris vulputate elementum. Nullam varius.","log_time":"2023-07-12 13:15:41"},
{"id":22,"module":"invoices","code":401,"message":"Donec ut dolor. Morbi vel lectus in quam fringilla rhoncus. Mauris enim leo, rhoncus sed, vestibulum sit amet, cursus id, turpis. Integer aliquet, massa id lobortis convallis, tortor risus dapibus augue, vel accumsan tellus nisi eu orci. Mauris lacinia sapien quis libero. Nullam sit amet turpis elementum ligula vehicula consequat. Morbi a ipsum. Integer a nibh.","log_time":"2022-10-16 11:36:49"},
{"id":23,"module":"products","code":504,"message":"Curabitur gravida nisi at nibh. In hac habitasse platea dictumst. Aliquam augue quam, sollicitudin vitae, consectetuer eget, rutrum at, lorem. Integer tincidunt ante vel ipsum. Praesent blandit lacinia erat. Vestibulum sed magna at nunc commodo placerat.","log_time":"2022-08-17 16:45:37"},
{"id":24,"module":"orders","code":401,"message":"Sed ante. Vivamus tortor. Duis mattis egestas metus. Aenean fermentum. Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh. Quisque id justo sit amet sapien dignissim vestibulum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Nulla dapibus dolor vel est. Donec odio justo, sollicitudin ut, suscipit a, feugiat et, eros. Vestibulum ac est lacinia nisi venenatis tristique.","log_time":"2023-03-15 22:59:02"},
{"id":25,"module":"orders","code":401,"message":"Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl. Aenean lectus. Pellentesque eget nunc. Donec quis orci eget orci vehicula condimentum.","log_time":"2023-04-30 02:41:33"},
{"id":26,"module":"invoices","code":504,"message":"Suspendisse ornare consequat lectus. In est risus, auctor sed, tristique in, tempus sit amet, sem. Fusce consequat. Nulla nisl. Nunc nisl. Duis bibendum, felis sed interdum venenatis, turpis enim blandit mi, in porttitor pede justo eu massa. Donec dapibus.","log_time":"2023-02-05 12:07:20"},
{"id":27,"module":"orders","code":500,"message":"Ut at dolor quis odio consequat varius. Integer ac leo.","log_time":"2023-03-03 05:11:33"},
{"id":28,"module":"subscriptions","code":504,"message":"Nam dui. Proin leo odio, porttitor id, consequat in, consequat ut, nulla. Sed accumsan felis. Ut at dolor quis odio consequat varius. Integer ac leo. Pellentesque ultrices mattis odio.","log_time":"2022-08-13 09:38:52"},
{"id":29,"module":"products","code":502,"message":"Nam congue, risus semper porta volutpat, quam pede lobortis ligula, sit amet eleifend pede libero quis orci. Nullam molestie nibh in lectus. Pellentesque at nulla. Suspendisse potenti. Cras in purus eu magna vulputate luctus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue.","log_time":"2022-10-19 21:05:40"},
{"id":30,"module":"products","code":500,"message":"Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo. Morbi ut odio. Cras mi pede, malesuada in, imperdiet et, commodo vulputate, justo. In blandit ultrices enim.","log_time":"2023-05-14 16:19:02"},
{"id":31,"module":"contacts","code":400,"message":"In quis justo. Maecenas rhoncus aliquam lacus. Morbi quis tortor id nulla ultrices aliquet. Maecenas leo odio, condimentum id, luctus nec, molestie sed, justo. Pellentesque viverra pede ac diam. Cras pellentesque volutpat dui. Maecenas tristique, est et tempus semper, est quam pharetra magna, ac consequat metus sapien ut nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris viverra diam vitae quam.","log_time":"2023-04-03 07:41:14"},
{"id":32,"module":"products","code":401,"message":"Quisque arcu libero, rutrum ac, lobortis vel, dapibus at, diam. Nam tristique tortor eu pede.","log_time":"2022-12-04 18:56:39"},
{"id":33,"module":"invoices","code":404,"message":"Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl. Aenean lectus. Pellentesque eget nunc. Donec quis orci eget orci vehicula condimentum. Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus. Vivamus in felis eu sapien cursus vestibulum.","log_time":"2022-10-12 21:52:50"},
{"id":34,"module":"subscriptions","code":401,"message":"In quis justo. Maecenas rhoncus aliquam lacus. Morbi quis tortor id nulla ultrices aliquet. Maecenas leo odio, condimentum id, luctus nec, molestie sed, justo. Pellentesque viverra pede ac diam. Cras pellentesque volutpat dui. Maecenas tristique, est et tempus semper, est quam pharetra magna, ac consequat metus sapien ut nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Mauris viverra diam vitae quam. Suspendisse potenti. Nullam porttitor lacus at turpis.","log_time":"2022-11-24 05:09:46"},
{"id":35,"module":"contacts","code":502,"message":"Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla. Sed vel enim sit amet nunc viverra dapibus. Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum.","log_time":"2022-09-17 04:17:43"},
{"id":36,"module":"subscriptions","code":500,"message":"Proin eu mi. Nulla ac enim.","log_time":"2023-05-31 11:44:43"},
{"id":37,"module":"subscriptions","code":504,"message":"Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Duis faucibus accumsan odio. Curabitur convallis. Duis consequat dui nec nisi volutpat eleifend. Donec ut dolor. Morbi vel lectus in quam fringilla rhoncus. Mauris enim leo, rhoncus sed, vestibulum sit amet, cursus id, turpis. Integer aliquet, massa id lobortis convallis, tortor risus dapibus augue, vel accumsan tellus nisi eu orci. Mauris lacinia sapien quis libero.","log_time":"2022-07-25 02:30:46"},
{"id":38,"module":"subscriptions","code":404,"message":"Aliquam non mauris. Morbi non lectus. Aliquam sit amet diam in magna bibendum imperdiet. Nullam orci pede, venenatis non, sodales sed, tincidunt eu, felis. Fusce posuere felis sed lacus.","log_time":"2022-10-26 23:44:08"},
{"id":39,"module":"orders","code":400,"message":"Fusce consequat. Nulla nisl. Nunc nisl. Duis bibendum, felis sed interdum venenatis, turpis enim blandit mi, in porttitor pede justo eu massa. Donec dapibus. Duis at velit eu est congue elementum. In hac habitasse platea dictumst.","log_time":"2023-06-20 13:42:05"},
{"id":40,"module":"invoices","code":200,"message":"In eleifend quam a odio. In hac habitasse platea dictumst. Maecenas ut massa quis augue luctus tincidunt. Nulla mollis molestie lorem. Quisque ut erat.","log_time":"2023-04-30 00:39:20"},
{"id":41,"module":"contacts","code":400,"message":"Duis ac nibh. Fusce lacus purus, aliquet at, feugiat non, pretium quis, lectus. Suspendisse potenti. In eleifend quam a odio. In hac habitasse platea dictumst. Maecenas ut massa quis augue luctus tincidunt. Nulla mollis molestie lorem.","log_time":"2023-06-26 22:32:43"},
{"id":42,"module":"contacts","code":500,"message":"Suspendisse potenti. Nullam porttitor lacus at turpis. Donec posuere metus vitae ipsum. Aliquam non mauris. Morbi non lectus. Aliquam sit amet diam in magna bibendum imperdiet.","log_time":"2023-05-07 23:35:09"},
{"id":43,"module":"products","code":401,"message":"Donec vitae nisi. Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla. Sed vel enim sit amet nunc viverra dapibus. Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum. Mauris ullamcorper purus sit amet nulla.","log_time":"2022-10-01 18:12:31"},
{"id":44,"module":"invoices","code":502,"message":"Ut at dolor quis odio consequat varius.","log_time":"2022-12-12 09:11:26"},
{"id":45,"module":"invoices","code":401,"message":"Praesent blandit lacinia erat.","log_time":"2023-06-18 13:38:46"},
{"id":46,"module":"products","code":200,"message":"Fusce consequat. Nulla nisl. Nunc nisl.","log_time":"2023-06-26 04:26:50"},
{"id":47,"module":"products","code":500,"message":"Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus. Vivamus in felis eu sapien cursus vestibulum. Proin eu mi.","log_time":"2022-08-06 03:31:52"},
{"id":48,"module":"products","code":401,"message":"Nulla mollis molestie lorem.","log_time":"2022-12-02 04:24:06"},
{"id":49,"module":"orders","code":502,"message":"Aenean lectus. Pellentesque eget nunc. Donec quis orci eget orci vehicula condimentum. Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus.","log_time":"2023-07-02 13:11:26"},
{"id":50,"module":"orders","code":504,"message":"Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Duis faucibus accumsan odio. Curabitur convallis. Duis consequat dui nec nisi volutpat eleifend. Donec ut dolor.","log_time":"2022-11-09 14:31:24"},
{"id":51,"module":"orders","code":504,"message":"Integer pede justo, lacinia eget, tincidunt eget, tempus vel, pede. Morbi porttitor lorem id ligula. Suspendisse ornare consequat lectus. In est risus, auctor sed, tristique in, tempus sit amet, sem. Fusce consequat.","log_time":"2023-01-22 07:22:36"},
{"id":52,"module":"contacts","code":400,"message":"Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo. Morbi ut odio. Cras mi pede, malesuada in, imperdiet et, commodo vulputate, justo.","log_time":"2023-07-22 08:11:59"},
{"id":53,"module":"products","code":200,"message":"Integer ac leo. Pellentesque ultrices mattis odio.","log_time":"2023-03-09 10:26:12"},
{"id":54,"module":"contacts","code":200,"message":"Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus. Vivamus in felis eu sapien cursus vestibulum. Proin eu mi. Nulla ac enim.","log_time":"2023-01-11 18:20:33"},
{"id":55,"module":"subscriptions","code":404,"message":"Cras pellentesque volutpat dui. Maecenas tristique, est et tempus semper, est quam pharetra magna, ac consequat metus sapien ut nunc.","log_time":"2022-10-28 15:00:04"},
{"id":56,"module":"subscriptions","code":200,"message":"Vestibulum rutrum rutrum neque.","log_time":"2022-08-16 16:26:45"},
{"id":57,"module":"orders","code":400,"message":"Nulla tellus. In sagittis dui vel nisl. Duis ac nibh. Fusce lacus purus, aliquet at, feugiat non, pretium quis, lectus. Suspendisse potenti.","log_time":"2023-07-14 05:26:12"},
{"id":58,"module":"orders","code":500,"message":"Nunc nisl. Duis bibendum, felis sed interdum venenatis, turpis enim blandit mi, in porttitor pede justo eu massa.","log_time":"2023-04-26 05:36:10"},
{"id":59,"module":"products","code":404,"message":"Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Proin interdum mauris non ligula pellentesque ultrices. Phasellus id sapien in sapien iaculis congue. Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl. Aenean lectus.","log_time":"2022-11-30 19:56:57"},
{"id":60,"module":"orders","code":404,"message":"Duis at velit eu est congue elementum. In hac habitasse platea dictumst. Morbi vestibulum, velit id pretium iaculis, diam erat fermentum justo, nec condimentum neque sapien placerat ante. Nulla justo. Aliquam quis turpis eget elit sodales scelerisque. Mauris sit amet eros.","log_time":"2023-05-04 14:21:28"},
{"id":61,"module":"orders","code":500,"message":"Sed ante. Vivamus tortor. Duis mattis egestas metus. Aenean fermentum. Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh. Quisque id justo sit amet sapien dignissim vestibulum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Nulla dapibus dolor vel est. Donec odio justo, sollicitudin ut, suscipit a, feugiat et, eros.","log_time":"2023-05-22 06:12:21"},
{"id":62,"module":"products","code":401,"message":"Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum. Mauris ullamcorper purus sit amet nulla. Quisque arcu libero, rutrum ac, lobortis vel, dapibus at, diam. Nam tristique tortor eu pede.","log_time":"2023-03-29 01:50:10"},
{"id":63,"module":"orders","code":502,"message":"Curabitur gravida nisi at nibh.","log_time":"2023-04-30 23:28:16"},
{"id":64,"module":"invoices","code":504,"message":"Duis mattis egestas metus. Aenean fermentum. Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh. Quisque id justo sit amet sapien dignissim vestibulum. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Nulla dapibus dolor vel est.","log_time":"2023-04-29 08:32:16"},
{"id":65,"module":"invoices","code":504,"message":"Curabitur gravida nisi at nibh. In hac habitasse platea dictumst.","log_time":"2022-09-29 23:53:55"},
{"id":66,"module":"products","code":500,"message":"Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo.","log_time":"2023-06-30 21:41:17"},
{"id":67,"module":"contacts","code":502,"message":"Donec vitae nisi. Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla. Sed vel enim sit amet nunc viverra dapibus. Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum. Mauris ullamcorper purus sit amet nulla. Quisque arcu libero, rutrum ac, lobortis vel, dapibus at, diam.","log_time":"2022-10-29 20:29:57"},
{"id":68,"module":"contacts","code":200,"message":"Pellentesque ultrices mattis odio. Donec vitae nisi. Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla. Sed vel enim sit amet nunc viverra dapibus. Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum. Mauris ullamcorper purus sit amet nulla. Quisque arcu libero, rutrum ac, lobortis vel, dapibus at, diam. Nam tristique tortor eu pede.","log_time":"2023-03-13 21:56:12"},
{"id":69,"module":"contacts","code":504,"message":"Phasellus in felis. Donec semper sapien a libero. Nam dui. Proin leo odio, porttitor id, consequat in, consequat ut, nulla. Sed accumsan felis. Ut at dolor quis odio consequat varius. Integer ac leo. Pellentesque ultrices mattis odio. Donec vitae nisi. Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla.","log_time":"2023-04-21 10:28:29"},
{"id":70,"module":"contacts","code":504,"message":"Proin leo odio, porttitor id, consequat in, consequat ut, nulla. Sed accumsan felis. Ut at dolor quis odio consequat varius. Integer ac leo. Pellentesque ultrices mattis odio. Donec vitae nisi. Nam ultrices, libero non mattis pulvinar, nulla pede ullamcorper augue, a suscipit nulla elit ac nulla. Sed vel enim sit amet nunc viverra dapibus. Nulla suscipit ligula in lacus. Curabitur at ipsum ac tellus semper interdum.","log_time":"2022-10-25 15:26:00"},
{"id":71,"module":"orders","code":404,"message":"Morbi non quam nec dui luctus rutrum. Nulla tellus. In sagittis dui vel nisl. Duis ac nibh.","log_time":"2022-11-25 09:03:27"},
{"id":72,"module":"invoices","code":500,"message":"Quisque porta volutpat erat. Quisque erat eros, viverra eget, congue eget, semper rutrum, nulla.","log_time":"2023-04-24 09:07:09"},
{"id":73,"module":"orders","code":404,"message":"Cras in purus eu magna vulputate luctus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.","log_time":"2022-07-25 06:55:55"},
{"id":74,"module":"invoices","code":401,"message":"Phasellus in felis. Donec semper sapien a libero. Nam dui. Proin leo odio, porttitor id, consequat in, consequat ut, nulla. Sed accumsan felis.","log_time":"2022-12-10 22:44:37"},
{"id":75,"module":"contacts","code":404,"message":"Morbi vel lectus in quam fringilla rhoncus. Mauris enim leo, rhoncus sed, vestibulum sit amet, cursus id, turpis. Integer aliquet, massa id lobortis convallis, tortor risus dapibus augue, vel accumsan tellus nisi eu orci. Mauris lacinia sapien quis libero. Nullam sit amet turpis elementum ligula vehicula consequat. Morbi a ipsum. Integer a nibh. In quis justo. Maecenas rhoncus aliquam lacus.","log_time":"2022-11-04 04:48:13"},
{"id":76,"module":"subscriptions","code":400,"message":"Etiam faucibus cursus urna. Ut tellus. Nulla ut erat id mauris vulputate elementum.","log_time":"2023-04-19 06:44:31"},
{"id":77,"module":"contacts","code":401,"message":"In hac habitasse platea dictumst. Maecenas ut massa quis augue luctus tincidunt. Nulla mollis molestie lorem.","log_time":"2023-07-11 05:47:44"},
{"id":78,"module":"invoices","code":404,"message":"Donec quis orci eget orci vehicula condimentum. Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus.","log_time":"2022-07-28 16:45:20"},
{"id":79,"module":"subscriptions","code":404,"message":"Vivamus tortor. Duis mattis egestas metus. Aenean fermentum. Donec ut mauris eget massa tempor convallis. Nulla neque libero, convallis eget, eleifend luctus, ultricies eu, nibh. Quisque id justo sit amet sapien dignissim vestibulum.","log_time":"2022-11-25 15:08:25"},
{"id":80,"module":"orders","code":401,"message":"Vivamus metus arcu, adipiscing molestie, hendrerit at, vulputate vitae, nisl. Aenean lectus. Pellentesque eget nunc. Donec quis orci eget orci vehicula condimentum.","log_time":"2022-12-28 21:08:38"},
{"id":81,"module":"orders","code":504,"message":"Donec odio justo, sollicitudin ut, suscipit a, feugiat et, eros. Vestibulum ac est lacinia nisi venenatis tristique. Fusce congue, diam id ornare imperdiet, sapien urna pretium nisl, ut volutpat sapien arcu sed augue. Aliquam erat volutpat. In congue. Etiam justo.","log_time":"2022-07-28 01:53:23"},
{"id":82,"module":"contacts","code":502,"message":"In hac habitasse platea dictumst. Etiam faucibus cursus urna. Ut tellus. Nulla ut erat id mauris vulputate elementum.","log_time":"2023-07-07 10:56:39"},
{"id":83,"module":"invoices","code":401,"message":"Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Duis faucibus accumsan odio.","log_time":"2022-09-12 18:01:25"},
{"id":84,"module":"invoices","code":502,"message":"Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo. Morbi ut odio.","log_time":"2023-04-03 02:45:10"},
{"id":85,"module":"products","code":502,"message":"Aliquam quis turpis eget elit sodales scelerisque. Mauris sit amet eros. Suspendisse accumsan tortor quis turpis. Sed ante. Vivamus tortor. Duis mattis egestas metus. Aenean fermentum.","log_time":"2023-04-10 12:38:18"},
{"id":86,"module":"contacts","code":502,"message":"Ut tellus. Nulla ut erat id mauris vulputate elementum. Nullam varius. Nulla facilisi.","log_time":"2023-04-10 12:53:00"},
{"id":87,"module":"orders","code":400,"message":"Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec pharetra, magna vestibulum aliquet ultrices, erat tortor sollicitudin mi, sit amet lobortis sapien sapien non mi.","log_time":"2023-05-14 11:41:21"},
{"id":88,"module":"subscriptions","code":500,"message":"Aliquam augue quam, sollicitudin vitae, consectetuer eget, rutrum at, lorem. Integer tincidunt ante vel ipsum. Praesent blandit lacinia erat. Vestibulum sed magna at nunc commodo placerat.","log_time":"2022-10-14 16:40:20"},
{"id":89,"module":"invoices","code":404,"message":"Integer ac neque. Duis bibendum. Morbi non quam nec dui luctus rutrum. Nulla tellus.","log_time":"2023-03-14 00:56:54"},
{"id":90,"module":"invoices","code":502,"message":"Integer non velit. Donec diam neque, vestibulum eget, vulputate ut, ultrices vel, augue. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Donec pharetra, magna vestibulum aliquet ultrices, erat tortor sollicitudin mi, sit amet lobortis sapien sapien non mi.","log_time":"2023-05-20 23:56:54"},
{"id":91,"module":"products","code":200,"message":"Curabitur in libero ut massa volutpat convallis. Morbi odio odio, elementum eu, interdum eu, tincidunt in, leo. Maecenas pulvinar lobortis est. Phasellus sit amet erat. Nulla tempus.","log_time":"2023-03-05 10:23:10"},
{"id":92,"module":"subscriptions","code":404,"message":"Duis bibendum, felis sed interdum venenatis, turpis enim blandit mi, in porttitor pede justo eu massa. Donec dapibus. Duis at velit eu est congue elementum. In hac habitasse platea dictumst. Morbi vestibulum, velit id pretium iaculis, diam erat fermentum justo, nec condimentum neque sapien placerat ante.","log_time":"2022-12-13 22:16:15"},
{"id":93,"module":"invoices","code":401,"message":"In congue. Etiam justo. Etiam pretium iaculis justo. In hac habitasse platea dictumst. Etiam faucibus cursus urna. Ut tellus. Nulla ut erat id mauris vulputate elementum. Nullam varius.","log_time":"2023-04-28 04:00:23"},
{"id":94,"module":"subscriptions","code":500,"message":"Vivamus vestibulum sagittis sapien. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo.","log_time":"2023-05-03 03:49:36"},
{"id":95,"module":"invoices","code":400,"message":"Maecenas rhoncus aliquam lacus. Morbi quis tortor id nulla ultrices aliquet. Maecenas leo odio, condimentum id, luctus nec, molestie sed, justo.","log_time":"2022-11-15 08:23:33"},
{"id":96,"module":"contacts","code":504,"message":"Nullam sit amet turpis elementum ligula vehicula consequat. Morbi a ipsum. Integer a nibh. In quis justo. Maecenas rhoncus aliquam lacus. Morbi quis tortor id nulla ultrices aliquet. Maecenas leo odio, condimentum id, luctus nec, molestie sed, justo. Pellentesque viverra pede ac diam.","log_time":"2022-11-06 11:12:40"},
{"id":97,"module":"contacts","code":400,"message":"Etiam vel augue. Vestibulum rutrum rutrum neque. Aenean auctor gravida sem. Praesent id massa id nisl venenatis lacinia. Aenean sit amet justo. Morbi ut odio. Cras mi pede, malesuada in, imperdiet et, commodo vulputate, justo. In blandit ultrices enim. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.","log_time":"2023-03-08 21:06:35"},
{"id":98,"module":"orders","code":200,"message":"Maecenas ut massa quis augue luctus tincidunt. Nulla mollis molestie lorem. Quisque ut erat. Curabitur gravida nisi at nibh.","log_time":"2023-06-03 10:37:19"},
{"id":99,"module":"contacts","code":502,"message":"Sed sagittis. Nam congue, risus semper porta volutpat, quam pede lobortis ligula, sit amet eleifend pede libero quis orci. Nullam molestie nibh in lectus. Pellentesque at nulla. Suspendisse potenti. Cras in purus eu magna vulputate luctus. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus.","log_time":"2023-02-16 22:49:59"},
{"id":100,"module":"contacts","code":400,"message":"Curabitur gravida nisi at nibh. In hac habitasse platea dictumst. Aliquam augue quam, sollicitudin vitae, consectetuer eget, rutrum at, lorem. Integer tincidunt ante vel ipsum. Praesent blandit lacinia erat. Vestibulum sed magna at nunc commodo placerat.","log_time":"2023-04-07 14:24:09"}]';

        $result = json_decode( $data, true );

        return [array(
                
                'module' => "contacts",
                'code' => 400,
                'message' => "Curabitur gravida nisi at nibh. In hac habitasse platea dictumst. Aliquam augue quam, sollicitudin vitae, consectetuer eget, rutrum at, lorem. Integer tincidunt ante vel ipsum. Praesent blandit lacinia erat. Vestibulum sed magna at nunc commodo placerat.",
                'log_time' => "2023-04-07 14:24:09"
            )];

    }

}