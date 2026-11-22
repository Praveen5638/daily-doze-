-- Daily Dose Export — production seed data
-- No prices, stock quantities, certifications, reviews or legal approvals are invented.
-- Variants are seeded with NULL price and 0 stock. The admin sets real values before launch.
-- Default admin: admin@dailydoseexport.com / ChangeMe@DDE2026 (change immediately after first login).

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- roles / staff
INSERT INTO admin_roles (id, name, slug, description) VALUES
(1, 'Super Admin', 'super_admin', 'Full access including staff and settings'),
(2, 'Admin', 'admin', 'All operations except staff management'),
(3, 'Order Manager', 'order_manager', 'Orders, customers, enquiries and reviews'),
(4, 'Inventory Manager', 'inventory_manager', 'Products, categories and stock'),
(5, 'Content Manager', 'content_manager', 'CMS, pages, banners and reviews');

INSERT INTO admin_permissions (role_id, permission) VALUES
(1,'dashboard.view'),(1,'products.manage'),(1,'categories.manage'),(1,'orders.manage'),(1,'customers.manage'),
(1,'inventory.manage'),(1,'coupons.manage'),(1,'enquiries.manage'),(1,'reviews.manage'),(1,'content.manage'),
(1,'settings.manage'),(1,'users.manage'),(1,'reports.view'),
(2,'dashboard.view'),(2,'products.manage'),(2,'categories.manage'),(2,'orders.manage'),(2,'customers.manage'),
(2,'inventory.manage'),(2,'coupons.manage'),(2,'enquiries.manage'),(2,'reviews.manage'),(2,'content.manage'),
(2,'settings.manage'),(2,'reports.view'),
(3,'dashboard.view'),(3,'orders.manage'),(3,'customers.manage'),(3,'enquiries.manage'),(3,'reviews.manage'),(3,'reports.view'),
(4,'dashboard.view'),(4,'products.manage'),(4,'categories.manage'),(4,'inventory.manage'),(4,'reports.view'),
(5,'dashboard.view'),(5,'content.manage'),(5,'reviews.manage');

INSERT INTO admins (id, role_id, name, email, password_hash, status) VALUES
(1, 1, 'Daily Dose Admin', 'admin@dailydoseexport.com', '$2y$10$pAs4aUte51fgAsPYNU52kO1mXXQPWjr6lN8Wslms0b6Ew3r7irFR6', 'active');

-- ---------------------------------------------------------------- settings
INSERT INTO website_settings (setting_key, setting_value, setting_group) VALUES
('site_name','Daily Dose Export','general'),
('tagline','Pure From Farm To World','general'),
('slogan','Every Bite, A Promise of Health.','general'),
('support_phone','+91 84299 33125','contact'),
('whatsapp_number','918429933125','contact'),
('contact_email','','contact'),
('address_line1','Bhojubeer','contact'),
('address_line2','Varanasi (Banaras)','contact'),
('state','Uttar Pradesh','contact'),
('pincode','','contact'),
('country','India','contact'),
('map_url','','contact'),
('instagram_url','','social'),
('facebook_url','','social'),
('youtube_url','','social'),
('x_url','','social'),
('gstin','','legal'),
('fssai_number','','legal'),
('legal_entity','','legal'),
('currency','INR','commerce'),
('tax_percent','0','commerce'),
('cod_enabled','0','commerce'),
('cod_fee','0','commerce'),
('shipping_flat_rate','0','commerce'),
('free_shipping_threshold','','commerce'),
('shipping_note','Delivery charges and timelines are confirmed by our team based on destination and order size.','commerce'),
('razorpay_enabled','0','payments'),
('razorpay_key_id','','payments'),
('announcement_text','','general'),
('announcement_link','','general'),
('footer_text','Natural Indian spices, grains, millets, teas and dry fruits for homes and businesses, packed with care in Varanasi.','general'),
('hero_eyebrow','Natural food · Varanasi, India','home'),
('hero_title','Pure From Farm To World','home'),
('hero_text','Natural spices, grains, millets, teas and dry fruits, sourced from Indian farms and packed with care in Varanasi.','home'),
('hero_image','assets/images/hero.jpg','home'),
('hero_note','Every Bite, A Promise of Health.','home'),
('about_intro','Daily Dose Export brings natural and agricultural food products from Indian farms to homes and businesses in India and around the world.','about'),
('mission','To Bring Nature''s Goodness to Every Home, With Quality and Care in Every Bite.','about'),
('vision','To Build a Healthier World Through Pure, Natural and Quality Food.','about'),
('core_values','Purity\nQuality\nCare for Every Generation\nFarmer Support\nHonesty\nSustainability\nCustomer Trust','about'),
('story_title','From Indian farms to your kitchen','about'),
('story_text','Daily Dose Export began in Varanasi with a simple belief: everyday food should be honest, well sourced and handled with care. We work with agricultural supply partners across India, focus on clean and careful processing, and package each product so it reaches you in good condition. Whether you are cooking for your family or sourcing for a business, our team is here to help.','about'),
('newsletter_title','Stay in touch','home'),
('newsletter_text','Receive product updates and sourcing notes from the Daily Dose Export team.','home'),
('default_meta_description','Daily Dose Export supplies natural Indian spices, grains, millets, cookies, teas, pink salt and dry fruits for retail, wholesale and export enquiries.','seo'),
('og_image','assets/images/hero.jpg','seo'),
('admin_notification_email','','general'),
('maintenance_mode','0','general'),
('whatsapp_default_message','Hello Daily Dose Export, I would like to know more about your products.','contact'),
('reviews_enabled','1','general');

-- Hindi storefront content (locale: hi)
INSERT INTO website_settings (setting_key, setting_value, setting_group) VALUES
('tagline_hi','खेत से दुनिया तक, शुद्ध','general'),
('slogan_hi','हर कौर, स्वास्थ्य का वादा।','general'),
('footer_text_hi','घरों और व्यवसायों के लिए प्राकृतिक भारतीय मसाले, अनाज, मिलेट, चाय और सूखे मेवे — वाराणसी में सावधानी से पैक।','general'),
('hero_eyebrow_hi','प्राकृतिक भोजन · वाराणसी, भारत','home'),
('hero_title_hi','खेत से दुनिया तक, शुद्ध','home'),
('hero_text_hi','भारतीय खेतों से प्राप्त प्राकृतिक मसाले, अनाज, मिलेट, चाय और सूखे मेवे — वाराणसी में सावधानी से पैक।','home'),
('hero_note_hi','हर कौर, स्वास्थ्य का वादा।','home'),
('about_intro_hi','डेली डोज़ एक्सपोर्ट भारतीय खेतों से प्राकृतिक और कृषि खाद्य उत्पाद भारत और दुनिया भर के घरों और व्यवसायों तक पहुँचाता है।','about'),
('mission_hi','प्रकृति की अच्छाई हर घर तक पहुँचाना — हर कौर में गुणवत्ता और देखभाल के साथ।','about'),
('vision_hi','शुद्ध, प्राकृतिक और गुणवत्तापूर्ण भोजन से एक स्वस्थ दुनिया बनाना।','about'),
('core_values_hi','शुद्धता\nगुणवत्ता\nहर पीढ़ी की देखभाल\nकिसान सहयोग\nईमानदारी\nसस्टेनेबिलिटी\nग्राहक विश्वास','about'),
('story_title_hi','भारतीय खेतों से आपकी रसोई तक','about'),
('story_text_hi','डेली डोज़ एक्सपोर्ट की शुरुआत वाराणसी में एक सरल विश्वास के साथ हुई: रोज़ का भोजन ईमानदार, अच्छी तरह प्राप्त और सावधानी से संभाला गया होना चाहिए। हम भारत भर के कृषि आपूर्ति साझेदारों के साथ काम करते हैं, साफ और सावधान प्रक्रिया पर ध्यान देते हैं, और हर उत्पाद को इस तरह पैक करते हैं कि वह अच्छी स्थिति में आप तक पहुँचे। आप परिवार के लिए पका रहे हों या व्यवसाय के लिए सोर्स कर रहे हों, हमारी टीम मदद के लिए यहाँ है।','about'),
('newsletter_title_hi','संपर्क में रहें','home'),
('newsletter_text_hi','डेली डोज़ एक्सपोर्ट टीम से उत्पाद अपडेट और सोर्सिंग नोट्स प्राप्त करें।','home'),
('default_meta_description_hi','डेली डोज़ एक्सपोर्ट खुदरा, थोक और निर्यात पूछताछ के लिए प्राकृतिक भारतीय मसाले, अनाज, मिलेट, कुकीज़, चाय, सेंधा नमक और सूखे मेवे उपलब्ध कराता है।','seo'),
('shipping_note_hi','डिलीवरी शुल्क और समय हमारी टीम गंतव्य और ऑर्डर के आकार के आधार पर पुष्टि करती है।','commerce')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group);

-- ---------------------------------------------------------------- categories
INSERT INTO categories (id, name, slug, description, image, sort_order, status) VALUES
(1,'Spices','spices','Whole and ground Indian spices sourced and packed with care.','assets/images/cat-spices.jpg',1,'active'),
(2,'Organic Grains','organic-grains','Everyday grains and agricultural produce from Indian farms.','assets/images/cat-grains.jpg',2,'active'),
(3,'Millets','millets','Traditional Indian millets for everyday meals.','assets/images/cat-millets.jpg',3,'active'),
(4,'Millet Cookies','millet-cookies','Cookies made with millet flours in small batches.','assets/images/cat-cookies.jpg',4,'active'),
(5,'Herbal Teas','herbal-teas','Tulsi green tea and Assam black tea.','assets/images/cat-teas.jpg',5,'active'),
(6,'Pink Salt','pink-salt','Himalayan pink salt and Sendha Namak.','assets/images/cat-salt.jpg',6,'active'),
(7,'Dates & Dry Fruits','dates-dry-fruits','Dates, almonds and cashews, packed to order.','assets/images/cat-dryfruits.jpg',7,'active');

-- ---------------------------------------------------------------- products
INSERT INTO products (id, category_id, name, slug, product_type, short_description, description, ingredients, storage_instructions, origin, packaging_info, is_featured, is_new, status, tax_rate, keywords, search_aliases) VALUES
(1,1,'Turmeric Powder','turmeric-powder','ground','Finely ground turmeric for everyday Indian cooking.','A kitchen staple sourced from Indian farms, cleaned, dried and finely ground. Packed in small batches for freshness.','Turmeric','Store in a cool, dry place in an airtight container, away from direct sunlight.','India','Food-grade pouch or jar, selected at packing.',1,0,'published',0,'turmeric, haldi, pure turmeric powder, buy turmeric online','Haldi, हल्दी, Haldar'),
(2,1,'Whole Turmeric','whole-turmeric','whole','Dried whole turmeric fingers, cleaned and graded.','Whole dried turmeric sourced from Indian farms and cleaned before packing. Grind at home or use in pickles and traditional preparations.','Turmeric','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'whole turmeric, turmeric fingers, haldi gath','Haldi, हल्दी'),
(3,1,'Red Chilli Powder','red-chilli-powder','ground','Ground red chilli with a warm, bright colour.','Red chilli sourced from Indian farms, dried and ground for daily cooking. Packed in small batches.','Red chilli','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'red chilli powder, lal mirch powder, buy chilli online','Lal Mirch, लाल मिर्च, Mirch'),
(4,1,'Whole Red Chilli','whole-red-chilli','whole','Whole dried red chillies, cleaned and graded.','Whole dried red chillies sourced from Indian farms. Use whole in tempering or grind at home.','Red chilli','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'whole red chilli, dried red chilli','Lal Mirch, साबुत लाल मिर्च'),
(5,1,'Ginger Powder','ginger-powder','ground','Dry ginger powder for teas, sweets and savoury dishes.','Dried ginger, ground and packed for everyday use in Indian kitchens.','Dried ginger','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'ginger powder, sonth, dry ginger','Sonth, Adrak, सौंठ, अदरक'),
(6,1,'Whole Dried Ginger','whole-dried-ginger','whole','Whole dried ginger, cleaned and packed.','Whole dried ginger sourced from Indian farms. Ideal for grinding at home or for traditional preparations.','Dried ginger','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'whole dried ginger, sonth sabut','Sonth, सौंठ'),
(7,1,'Green Cardamom','green-cardamom','whole','Whole green cardamom pods, cleaned and graded.','Green cardamom pods sourced from Indian farms. A fragrant addition to tea, sweets and savoury dishes.','Green cardamom','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'green cardamom, elaichi, buy cardamom online','Elaichi, इलायची'),
(8,1,'Coriander Powder','coriander-powder','ground','Ground coriander for everyday Indian cooking.','Coriander seeds cleaned, dried and ground for daily use. Packed in small batches.','Coriander','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'coriander powder, dhaniya powder','Dhaniya, धनिया'),
(9,1,'Garam Masala','garam-masala','blend','A warm Indian spice blend for everyday cooking.','A traditional-style garam masala blend prepared for everyday Indian dishes. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'garam masala, spice blend','Garam Masala, गरम मसाला'),
(10,1,'Black Pepper','black-pepper','whole','Whole black peppercorns, cleaned and graded.','Whole black peppercorns sourced from Indian farms. Grind fresh or use whole in cooking.','Black pepper','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'black pepper, kali mirch, peppercorn','Kali Mirch, काली मिर्च'),
(11,1,'Cloves','cloves','whole','Whole cloves, cleaned and packed.','Whole cloves for tempering, teas and traditional preparations.','Cloves','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'cloves, laung, buy cloves online','Laung, लौंग'),
(12,2,'Organic Wheat','organic-wheat','grain','Whole wheat grain for home milling and everyday cooking.','Whole wheat grain sourced through our agricultural supply partners. Cleaned and packed to order. Organic certification details are shared only when verified and printed on the pack.','Wheat','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'organic wheat, gehu, whole wheat grain','Gehu, गेहूं, गेहूँ'),
(13,2,'Organic Rice','organic-rice','grain','Whole rice grain, cleaned and packed.','Rice sourced through our agricultural supply partners, cleaned and packed for households and bulk buyers. Variety and organic status are confirmed on the approved pack.','Rice','Store in a cool, dry place in an airtight container.','India',NULL,0,1,'published',0,'organic rice, chawal, rice grain','Chawal, चावल'),
(14,3,'Ragi','ragi','millet','Finger millet grain, cleaned and packed.','Ragi (finger millet) sourced from Indian farms, cleaned and packed for everyday use.','Ragi (finger millet)','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'ragi, finger millet, buy ragi online','Ragi, रागी, Mandua, मंडुआ'),
(15,3,'Bajra','bajra','millet','Pearl millet grain, cleaned and packed.','Bajra (pearl millet) sourced from Indian farms, cleaned and packed for rotis and traditional dishes.','Bajra (pearl millet)','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'bajra, pearl millet, buy bajra online','Bajra, बाजरा'),
(16,3,'Jowar','jowar','millet','Sorghum grain, cleaned and packed.','Jowar (sorghum) sourced from Indian farms, cleaned and packed for rotis and everyday meals.','Jowar (sorghum)','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'jowar, sorghum, buy jowar online','Jowar, ज्वार'),
(17,3,'Kodo Millet','kodo-millet','millet','Kodo millet grain, cleaned and packed.','Kodo millet sourced from Indian farms, cleaned and packed for everyday cooking.','Kodo millet','Store in a cool, dry place in an airtight container.','India',NULL,0,1,'published',0,'kodo millet, kodra, buy kodo millet','Kodo, Kodra, कोदो'),
(18,3,'Multi-Millet Mix','multi-millet-mix','millet','A mix of traditional millets for everyday meals.','A blend of millet grains prepared for rotis, porridge and traditional dishes. Exact blend proportions are printed on the approved pack.','Millets','Store in a cool, dry place in an airtight container.','India',NULL,0,1,'published',0,'multi millet mix, millet blend','Millet Mix, मिलेट मिक्स'),
(19,4,'Bajra Millet Cookies','bajra-millet-cookies','cookie','Crunchy cookies made with bajra flour.','Cookies made with bajra (pearl millet) flour in small batches. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place. Consume before the date printed on the pack.','India',NULL,1,0,'published',0,'bajra cookies, millet cookies, buy bajra cookies','Bajra Cookies, बाजरा कुकीज़'),
(20,4,'Ragi Millet Cookies','ragi-millet-cookies','cookie','Crunchy cookies made with ragi flour.','Cookies made with ragi (finger millet) flour in small batches. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place. Consume before the date printed on the pack.','India',NULL,1,0,'published',0,'ragi cookies, finger millet cookies, millet cookies online','Ragi Cookies, रागी कुकीज़'),
(21,4,'Jowar Coconut Millet Cookies','jowar-coconut-millet-cookies','cookie','Jowar cookies with coconut.','Cookies made with jowar (sorghum) flour and coconut in small batches. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place. Consume before the date printed on the pack.','India',NULL,0,1,'published',0,'jowar coconut cookies, jowar cookies, millet cookies','Jowar Cookies, ज्वार कुकीज़'),
(22,4,'Multigrain Cookies','multigrain-cookies','cookie','Cookies made with a multigrain flour blend.','Multigrain cookies baked in small batches. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place. Consume before the date printed on the pack.','India',NULL,0,0,'published',0,'multigrain cookies, healthy cookies india','Multigrain Cookies, मल्टीग्रेन कुकीज़'),
(23,4,'Assorted Millet Cookies','assorted-millet-cookies','cookie','A selection of millet cookies in one pack.','An assortment of millet cookies for gifting and everyday snacking. Ingredient and allergen details are printed on the approved pack.','','Store in a cool, dry place. Consume before the date printed on the pack.','India',NULL,1,0,'published',0,'assorted cookies, millet cookie gift pack','Assorted Cookies, मिलेट कुकीज़'),
(24,5,'Tulsi Green Tea','tulsi-green-tea','tea','Tulsi and green tea leaves, packed for daily cups.','A tulsi and green tea blend prepared for everyday brewing. Ingredient details are printed on the approved pack.','Tulsi, green tea','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'tulsi green tea, herbal tea, tulsi tea online','Tulsi Chai, तुलसी ग्रीन टी'),
(25,5,'Assam Black Tea','assam-black-tea','tea','Assam black tea leaves for a strong daily cup.','Black tea from Assam, selected and packed for households and bulk buyers.','Assam black tea','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'assam black tea, black tea online','Assam Chai, असम ब्लैक टी'),
(26,6,'Himalayan Pink Salt','himalayan-pink-salt','salt','Coarse Himalayan pink salt, packed for daily use.','Himalayan pink salt selected, cleaned and packed for kitchens and bulk buyers. Mineral content is not standardised and is not claimed.','Himalayan pink salt (rock salt)','Store in a cool, dry place in an airtight container.','Himalayan region, India',NULL,1,0,'published',0,'himalayan pink salt, pink salt online, sendha namak','Pink Salt, सेंधा नमक'),
(27,6,'Sendha Namak','sendha-namak','salt','Rock salt for everyday and traditional cooking.','Sendha namak (rock salt) cleaned and packed for everyday cooking and traditional preparations.','Sendha namak (rock salt)','Store in a cool, dry place in an airtight container.','India',NULL,0,0,'published',0,'sendha namak, rock salt, upvas salt','Sendha Namak, सेंधा नमक'),
(28,7,'Almonds','almonds','dry-fruit','Whole almonds, cleaned and packed.','Whole almonds packed for households and bulk buyers. Origin and grade are confirmed on the approved pack.','Almonds','Store in a cool, dry place in an airtight container.','India',NULL,1,0,'published',0,'almonds, badam, buy almonds online','Badam, बादाम'),
(29,7,'Cashews','cashews','dry-fruit','Whole cashews, cleaned and packed.','Whole cashews packed for households and bulk buyers. Grade and origin are confirmed on the approved pack.','Cashews','Store in a cool, dry place in an airtight container.','India',NULL,0,1,'published',0,'cashews, kaju, buy cashew online','Kaju, काजू'),
(30,7,'Premium Dates','premium-dates','date','Soft dates packed for everyday snacking.','Dates selected and packed for households and bulk buyers. Variety and origin are confirmed on the approved pack.','Dates','Store in a cool, dry place. Refrigerate after opening in hot weather.','India',NULL,0,0,'published',0,'premium dates, khajoor, buy dates online','Khajoor, खजूर'),
(31,7,'Medjool Dates','medjool-dates','date','Large Medjool-style dates, packed to order.','Large soft dates selected and packed for gifting and everyday snacking. Origin is confirmed on the approved pack.','Dates','Store in a cool, dry place. Refrigerate after opening in hot weather.','Imported',NULL,1,0,'published',0,'medjool dates, premium dates, buy medjool dates','Medjool Khajoor, मेडजूल खजूर');

-- ---------------------------------------------------------------- product images (category placeholder until real photography)
INSERT INTO product_images (product_id, image_path, alt_text, sort_order, is_primary)
SELECT p.id, c.image, p.name, 0, 1 FROM products p JOIN categories c ON c.id = p.category_id;

-- ---------------------------------------------------------------- variants (price and stock intentionally unset)
INSERT INTO product_variants (product_id, sku, pack_size, weight_grams, is_default) VALUES
(1,'DDE-TUR-PWD-50','50 g',50,0),(1,'DDE-TUR-PWD-100','100 g',100,1),(1,'DDE-TUR-PWD-200','200 g',200,0),(1,'DDE-TUR-PWD-500','500 g',500,0),(1,'DDE-TUR-PWD-1KG','1 kg',1000,0),
(2,'DDE-TUR-WHL-100','100 g',100,1),(2,'DDE-TUR-WHL-500','500 g',500,0),(2,'DDE-TUR-WHL-1KG','1 kg',1000,0),
(3,'DDE-RCL-PWD-100','100 g',100,1),(3,'DDE-RCL-PWD-200','200 g',200,0),(3,'DDE-RCL-PWD-500','500 g',500,0),
(4,'DDE-RCL-WHL-100','100 g',100,1),(4,'DDE-RCL-WHL-500','500 g',500,0),
(5,'DDE-GIN-PWD-100','100 g',100,1),(5,'DDE-GIN-PWD-200','200 g',200,0),(5,'DDE-GIN-PWD-500','500 g',500,0),
(6,'DDE-GIN-WHL-100','100 g',100,1),(6,'DDE-GIN-WHL-500','500 g',500,0),
(7,'DDE-CRD-GRN-50','50 g',50,1),(7,'DDE-CRD-GRN-100','100 g',100,0),(7,'DDE-CRD-GRN-250','250 g',250,0),
(8,'DDE-COR-PWD-100','100 g',100,1),(8,'DDE-COR-PWD-500','500 g',500,0),
(9,'DDE-GMS-100','100 g',100,1),(9,'DDE-GMS-250','250 g',250,0),
(10,'DDE-PEP-WHL-100','100 g',100,1),(10,'DDE-PEP-WHL-500','500 g',500,0),
(11,'DDE-CLV-WHL-50','50 g',50,1),(11,'DDE-CLV-WHL-100','100 g',100,0),
(12,'DDE-WHT-1KG','1 kg',1000,1),(12,'DDE-WHT-5KG','5 kg',5000,0),
(13,'DDE-RIC-1KG','1 kg',1000,1),(13,'DDE-RIC-5KG','5 kg',5000,0),
(14,'DDE-RAG-500','500 g',500,0),(14,'DDE-RAG-1KG','1 kg',1000,1),(14,'DDE-RAG-5KG','5 kg',5000,0),
(15,'DDE-BAJ-500','500 g',500,0),(15,'DDE-BAJ-1KG','1 kg',1000,1),(15,'DDE-BAJ-5KG','5 kg',5000,0),
(16,'DDE-JOW-500','500 g',500,0),(16,'DDE-JOW-1KG','1 kg',1000,1),(16,'DDE-JOW-5KG','5 kg',5000,0),
(17,'DDE-KOD-500','500 g',500,0),(17,'DDE-KOD-1KG','1 kg',1000,1),
(18,'DDE-MMX-500','500 g',500,1),(18,'DDE-MMX-1KG','1 kg',1000,0),
(19,'DDE-CKB-150','150 g',150,1),(19,'DDE-CKB-300','300 g',300,0),
(20,'DDE-CKR-150','150 g',150,1),(20,'DDE-CKR-300','300 g',300,0),
(21,'DDE-CKJ-150','150 g',150,1),(21,'DDE-CKJ-300','300 g',300,0),
(22,'DDE-CKM-150','150 g',150,1),(22,'DDE-CKM-300','300 g',300,0),
(23,'DDE-CKA-200','200 g',200,1),(23,'DDE-CKA-400','400 g',400,0),
(24,'DDE-TEA-TUL-50','50 g',50,1),(24,'DDE-TEA-TUL-100','100 g',100,0),
(25,'DDE-TEA-ASM-100','100 g',100,1),(25,'DDE-TEA-ASM-250','250 g',250,0),
(26,'DDE-SLT-PNK-500','500 g',500,1),(26,'DDE-SLT-PNK-1KG','1 kg',1000,0),
(27,'DDE-SLT-SND-500','500 g',500,1),(27,'DDE-SLT-SND-1KG','1 kg',1000,0),
(28,'DDE-ALM-250','250 g',250,1),(28,'DDE-ALM-500','500 g',500,0),(28,'DDE-ALM-1KG','1 kg',1000,0),
(29,'DDE-CSH-250','250 g',250,1),(29,'DDE-CSH-500','500 g',500,0),
(30,'DDE-DAT-PRM-500','500 g',500,1),(30,'DDE-DAT-PRM-1KG','1 kg',1000,0),
(31,'DDE-DAT-MDJ-250','250 g',250,1),(31,'DDE-DAT-MDJ-500','500 g',500,0);

INSERT INTO product_inventory (variant_id, stock_qty, low_stock_threshold)
SELECT id, 0, 10 FROM product_variants;

-- ---------------------------------------------------------------- team
INSERT INTO team_members (name, position, bio, sort_order, status) VALUES
('Pooja Maurya','Chief Executive Officer',NULL,1,'active'),
('Yaswant Maurya','Managing Director',NULL,2,'active');

-- ---------------------------------------------------------------- CMS pages (draft where owner approval is required)
INSERT INTO cms_pages (title, slug, body, meta_title, meta_description, status) VALUES
('Privacy Policy','privacy-policy','<p><strong>Draft — pending owner and legal review.</strong></p><p>This policy will explain what personal information Daily Dose Export collects, why it is collected, how it is used, how long it is retained and how customers can request access or deletion.</p><p>Data currently collected by this website: account details, delivery addresses, order details, contact and enquiry messages, and technical logs required to operate the service securely.</p><p>For privacy requests, please contact us on the phone number shown on the Contact page.</p>','Privacy Policy | Daily Dose Export','How Daily Dose Export handles personal information. Draft pending owner review.','draft'),
('Terms and Conditions','terms-and-conditions','<p><strong>Draft — pending owner and legal review.</strong></p><p>These terms will govern the use of dailydoseexport.com, product orders, pricing, delivery, cancellations, returns and dispute resolution.</p><p>Product information is displayed from records maintained by Daily Dose Export. Final pack details, net quantity and declarations are those printed on the approved product packaging.</p>','Terms and Conditions | Daily Dose Export','Website and order terms for Daily Dose Export. Draft pending owner review.','draft'),
('Shipping Policy','shipping-policy','<p><strong>Draft — pending owner and legal review.</strong></p><p>Shipping charges, courier partners, serviceable PIN codes and delivery timelines will be published here once confirmed by the business.</p><p>Orders are packed at our Varanasi facility. Tracking details are shared after dispatch where available.</p>','Shipping Policy | Daily Dose Export','Shipping and delivery information. Draft pending owner review.','draft'),
('Return and Refund Policy','return-refund-policy','<p><strong>Draft — pending owner and legal review.</strong></p><p>This policy will set out the conditions for returns, replacements and refunds for food products, including reporting damaged or incorrect items within the period confirmed by the business.</p><p>For urgent help with an order, please contact us using the phone number on the Contact page.</p>','Return and Refund Policy | Daily Dose Export','Returns and refunds for Daily Dose Export orders. Draft pending owner review.','draft'),
('Cancellation Policy','cancellation-policy','<p><strong>Draft — pending owner and legal review.</strong></p><p>This policy will explain when an order can be cancelled, how cancellations are processed and how prepaid refunds are returned.</p>','Cancellation Policy | Daily Dose Export','Order cancellation information. Draft pending owner review.','draft'),
('Cookie Policy','cookie-policy','<p><strong>Draft — pending owner and legal review.</strong></p><p>This website uses essential cookies for login sessions, the shopping cart and security. Analytics or marketing cookies are not enabled unless configured by the business and disclosed here.</p>','Cookie Policy | Daily Dose Export','Cookies used by dailydoseexport.com. Draft pending owner review.','draft'),
('Frequently Asked Questions','faq','<h2>Ordering</h2><p><strong>Can I order without creating an account?</strong><br>Yes. Guest checkout is available. You can also create an account to save addresses and view order history.</p><h2>Wholesale and export</h2><p><strong>Do you supply bulk quantities?</strong><br>Yes. Use the Wholesale page for domestic bulk enquiries or the Export Enquiry page for international requirements. Our team will confirm availability, packing and pricing.</p><h2>Product information</h2><p><strong>Where are ingredients and allergen details?</strong><br>Approved declarations are printed on the product pack. Where a declaration is not yet published on this website, our team can share the details for the specific batch.</p><h2>Payments</h2><p><strong>Which payment methods are available?</strong><br>Available methods are shown at checkout and may include online payment and cash on delivery where enabled by the business.</p>','Frequently Asked Questions | Daily Dose Export','Ordering, guest checkout, wholesale and export enquiries, payments and product information — answered by Daily Dose Export, Varanasi.','published');

-- ---------------------------------------------------------------- shipping defaults
INSERT INTO shipping_methods (name, description, base_rate, free_over_amount, estimated_days, status, sort_order) VALUES
('Standard Delivery','Delivery timeline is confirmed by our team based on destination.',0.00,NULL,NULL,'active',1);

-- ---------------------------------------------------------------- navigation-friendly settings already seeded above.
-- No reviews, coupons, orders or stock quantities are seeded.
