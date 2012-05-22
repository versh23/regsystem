#include <amxmodx>
#include <amxmisc>
#include <sqlx>
/*
CREATE TABLE IF NOT EXISTS `ibf_srr_steam` (
  `id` int(11) NOT NULL auto_increment,
  `hddsn` varchar(20) NOT NULL,
  `steam` varchar(42) NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
CREATE TABLE IF NOT EXISTS `ibf_srr_hdd` (
  `id` int(11) NOT NULL auto_increment,
  `forum_id` int(11) default NULL,
  `hddsn` varchar(20) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

*/
//new pcv_host, pcv_db, pcv_user, pcv_password;
new Handle:g_SQLTuple;
new g_Reason; //1 - esli banned
new Float:g_PrevRegAttemptTime[33];
//new Float:g_Point;new Float:g_Point2;
new arg[1];
public plugin_init() {
	register_plugin("Reg System", "0.4.3", "lee")
	/*
	pcv_host = register_cvar("srr_stats_host", "127.0.0.1");
	pcv_db = register_cvar("srr_stats_db", "test");
	pcv_user = register_cvar("srr_stats_user", "root");
	pcv_password = register_cvar("srr_stats_password", "");
	*/	
	register_clcmd("reg_forum", "cmdRegForum");
	register_srvcmd("reg_kick", "cmdRegKick");
}


public plugin_cfg() {	
	/*
	new host[64], db[64], user[64], password[64];
	get_pcvar_string(pcv_host, host, charsmax(hostf));
	get_pcvar_string(pcv_db, db, charsmax(db));
	get_pcvar_string(pcv_user, user, charsmax(user));
	get_pcvar_string(pcv_password, password, charsmax(password));
	*/
	g_SQLTuple = SQL_MakeDbTuple("host", "login", "pass", "db_name")
}

public cmdRegKick()
{
	
	new b_arg[33];
	read_argv(1, b_arg, 32);
	new id=str_to_num(b_arg);	
	g_Reason =1;
	//log_amx("BANNED AND REG");
	cmdRegForum(id);
	return PLUGIN_HANDLED;
	
}
public cmdRegForum(id) {
	
	if (g_Reason==0)
	{
		if (!is_user_connected(id)) return PLUGIN_HANDLED;
	}
	
	if (get_timeleft()<40) return PLUGIN_HANDLED;	
	new hddsn[32];	
	get_user_info(id, "*hwID", hddsn, 32);
	new user_uid = get_user_userid(id);	
	if (strlen(hddsn) <= 0)
	{	
		if(g_Reason==1)
		{
			server_cmd("kick #%d ^"Вы были забанены. Подробно на сайте stalin-server.ru. У Вас не включен АнтиЧит myAC. В регистрации отказано!^"^n", user_uid);	
			g_Reason=0;
			return PLUGIN_HANDLED;
		}else{
			server_cmd("kick #%d ^"У Вас не включен АнтиЧит myAC. В регистрации отказано!^"^n", user_uid);	
			//console_print(id,"У вас не включен АнтиЧит MYAC.В регистрации отказано!");
			return PLUGIN_HANDLED;
		}
		
	}
	if ((get_gametime() - g_PrevRegAttemptTime[id]) < 20.0) {
		if(g_Reason ==1)
		{
			server_cmd("kick #%d ^"Вы были забанены. Подробно на сайте stalin-server.ru Регистрация доступна через %f секунд^"^n", user_uid,20.0 - (get_gametime() - g_PrevRegAttemptTime[id]));
			g_Reason=0;
			return PLUGIN_HANDLED;
		}else{
			server_cmd("kick #%d ^"Следующий запрос на регистрацию можете послать через %f секунд^"^n", user_uid,20.0 - (get_gametime() - g_PrevRegAttemptTime[id]));
			return PLUGIN_HANDLED;
		}
		//client_print(id, print_console, "next test_reg in  %f seconds", 20.0 - (get_gametime() - g_PrevRegAttemptTime[id]));
		
	}
	g_PrevRegAttemptTime[id] = get_gametime();
	//debug
	//g_Point=get_gametime();
	//proverim, est' li takou' yje steam id? esli da, to otkajem v registracii
	//SELECT id FROM `ibf_srr_steam` WHERE `steam` = 
	new steam[32];get_user_authid(id, steam, 31);
	new s_Query[128];
	format(s_Query, charsmax(s_Query), "SELECT id FROM ibf_srr_steam WHERE steam='%s'",steam );
	new qData[1];
	qData[0]=id;	
	SQL_ThreadQuery(g_SQLTuple, "Check_ex_steam", s_Query,qData,1);
	return PLUGIN_HANDLED;
}
public Check_ex_steam(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query failed.")
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
    else
        if (SQL_NumResults(h_Query))
        {	
			//new user_uid = get_user_userid(s_Data[0]);				
			//server_cmd("kick #%d ^"Ваш идентификатор уже существует! Обратитесь к администратору!^"^n", user_uid);
			//console_print(id,"this steam_id already exist, sucker!");
			new s_Query[380];
			new qData[1];
			qData[0]=s_Data[0];
			new steam[32];get_user_authid(s_Data[0], steam, 31);
			format(s_Query, charsmax(s_Query), "SELECT ibf_srr_hdd.id FROM ibf_srr_hdd INNER JOIN ibf_srr_steam ON ibf_srr_hdd.hddsn = ibf_srr_steam.hddsn WHERE ibf_srr_steam.steam='%s'", steam);
			log_amx("[REGSYSTEM] Check_ex_steam - >> %f",f_QueueTime);				
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "Check_hddsn", s_Query,qData,1);
	
        }else{		
			new hddsn[32];	
			get_user_info(s_Data[0], "*hwID", hddsn, 32);				
			new s_Query[128];
			new qData[1];
			qData[0]=s_Data[0];			
			format(s_Query, charsmax(s_Query), "SELECT id FROM ibf_srr_hdd WHERE hddsn='%s'", hddsn);
			log_amx("[REGSYSTEM] Check_ex_steam2 - >> %f",f_QueueTime);			
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "Check_hddsn", s_Query,qData,1);
		}
}


public update_steam(arg[]){
	new id = arg[0];		
	//зареган он или нет?
	if (!is_user_connected(id)) return PLUGIN_HANDLED;	
	new hddsn[32];//new steam[32];
	//get_user_authid(id, steam, 31);
	get_user_info(id, "*hwID", hddsn, 32) 	
	if (strlen(hddsn) <= 0) return PLUGIN_HANDLED;
	//debug
	//g_Point2 = get_gametime();
	new s_Query[384];
	new dt[32];get_time("%Y-%m-%d %X",dt,31) 	
	//format(s_Query, charsmax(s_Query), "SELECT ibf_srr_hdd.forum_id FROM ibf_srr_hdd INNER JOIN ibf_srr_steam ON ibf_srr_hdd.hddsn = ibf_srr_steam.hddsn WHERE ibf_srr_steam.date < '%s' AND ibf_srr_steam.hddsn = '%s' AND ibf_srr_steam.steam = '%s' LIMIT 1",dt, hddsn,steam);
	//log_amx("update query - > %s",s_Query);
	format(s_Query, charsmax(s_Query), "SELECT forum_id FROM ibf_srr_hdd WHERE hddsn='%s'", hddsn);
	//log_amx("update query - > %s",s_Query);
	new qData[1];
	qData[0]=id;	
	SQL_ThreadQuery(g_SQLTuple, "Check_reg", s_Query,qData,1);
	return PLUGIN_HANDLED;
}
public client_putinserver(id){
	if (get_timeleft()<40) return PLUGIN_HANDLED;
	arg[0] = id;
	set_task(20.0,"update_steam",_,arg,1);
	return PLUGIN_CONTINUE;
}
public Check_reg(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
	if (i_FailState == TQUERY_CONNECT_FAILED) set_fail_state("Could not connect to database.")
	else if (i_FailState == TQUERY_QUERY_FAILED) set_fail_state("Query failed.")
	else if (i_Errcode) log_amx("Error on query: %s", s_Error)
	else 
	if (SQL_NumResults(h_Query))//он зареган на форуме
	{
			//проверим есть ли стим
			new forum_id[4];
			SQL_ReadResult(h_Query, 0, forum_id, charsmax(forum_id));
			if (equali(forum_id,"")) {
				//log_amx("[REGSYSTEM] not registered on forum - >> %f",get_gametime()-g_Point2);
				return PLUGIN_HANDLED;
			}
			new steam[32];
			new s_Query[128];
			get_user_authid(s_Data[0], steam, 31);
			//log_amx("[REGSYSTEM] is registered on forum - >> %f",get_gametime()-g_Point2);
			format(s_Query, charsmax(s_Query), "SELECT id FROM ibf_srr_steam WHERE steam='%s'", steam);
			//log_amx("[REGSYSTEM] Check_reg %s",s_Query);
			new qData[1];
			qData[0]=s_Data[0];	
			log_amx("[REGSYSTEM] Check_reg - >> %f",f_QueueTime);
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "Check_steam", s_Query,qData,1);
	}else{
			//log_amx("[REGSYSTEM] not registered on forum - >> %f",get_gametime()-g_Point2);
			log_amx("[REGSYSTEM] Check_reg2 - >> %f",f_QueueTime);
			return PLUGIN_CONTINUE;
	}
	return PLUGIN_CONTINUE;
}
public Check_steam(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query Check_steam failed." )
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
    else
        if (!SQL_NumResults(h_Query))//такой стим уже есть, тогда обновляем
	{		
			//log_amx("[REGSYSTEM] lets inserrt");
			//insert
			new steam[32];
			new dt[32];
			new hddsn[32];	
			get_user_info(s_Data[0], "*hwID", hddsn, 32) 	
			get_time("%Y-%m-%d %X",dt,31) 
			new s_Query[128];
			get_user_authid(s_Data[0], steam, 31);
			format(s_Query, charsmax(s_Query), "INSERT INTO `ibf_srr_steam` (`hddsn`, `steam`, `date`) VALUES ('%s', '%s', '%s')",hddsn, steam,dt);
			//log_amx("[REGSYSTEM] %s",s_Query);
			log_amx("[REGSYSTEM] Check_steam - >> %f",f_QueueTime);
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "q_handle", s_Query);
	}else{//нет такого, тогда добавляем
		//update dt
			//log_amx("[REGSYSTEM] lets update");
			new steam[32];
			new dt[32];
			get_time("%Y-%m-%d %X",dt,31) 
			new s_Query[128];
			get_user_authid(s_Data[0], steam, 31);
			format(s_Query, charsmax(s_Query), "UPDATE `ibf_srr_steam` SET `date` = '%s' WHERE `steam` ='%s'",dt, steam);
			//log_amx("[REGSYSTEM] %s",s_Query);
			log_amx("[REGSYSTEM] Check_steam2 - >> %f",f_QueueTime);
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "q_handle", s_Query);
	}

}

public q_handle(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query q_handle failed.")
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
	//else log_amx("[REGSYSTEM] end of insert/update - >> %f",get_gametime()-g_Point2);
	else log_amx("[REGSYSTEM] q_handle - >> %f",f_QueueTime);
}

public Check_hddsn(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query failed.")
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
    else
        if (SQL_NumResults(h_Query))
        {						
			new regNumber[13];
			SQL_ReadResult(h_Query, 0, regNumber, charsmax(regNumber));
			new hash1[6];new hash2[4];
			for( new i = 0; i < sizeof(hash1) - 1; i++ )
			{
					hash1[i] = random_num('0', '9');
			}
			for( new i = 0; i < sizeof(hash2) - 1; i++ )
			{
					hash2[i] = random_num('a', 'z');
			}
		
			new user_uid = get_user_userid(s_Data[0]);				
			if(g_Reason == 1)
			{
				server_cmd("kick #%d ^"Вы были забанены. Подробно на сайте stalin-server.ru Ваш регистрационный номер: %s%s%s^"^n", user_uid,hash1,regNumber,hash2);
				g_Reason =0;
			}else{
				server_cmd("kick #%d ^"Ваш регистрационный номер: %s%s%s^"^n", user_uid,hash1,regNumber,hash2);
			}
			
			//console_print(s_Data[0],"регистрационный номер %s%s%s",hash1,regNumber,hash2);
			//log_amx("[REGSYSTEM] output ID - >> %f",get_gametime()-g_Point);
			log_amx("[REGSYSTEM] Check_hddsn - >> %f",f_QueueTime);
        }else{		
			new hddsn[32];	
			new steam[32];
			new dt[32];
			get_time("%Y-%m-%d %X",dt,31);			
			get_user_authid(s_Data[0], steam, 31);
			get_user_info(s_Data[0], "*hwID", hddsn, 32);				
			new s_Query[256];
			new qData[1];
			qData[0]=s_Data[0];			
			format(s_Query, charsmax(s_Query), "INSERT INTO ibf_srr_hdd (hddsn) VALUES ('%s');INSERT INTO `ibf_srr_steam` (`hddsn`, `steam`, `date`) VALUES ('%s', '%s', '%s')",hddsn,hddsn, steam,dt);
			//log_amx("[REGSYSTEM] insert hddsn - >> %f",get_gametime()-g_Point);
			//log_amx("[REGSYSTEM] insert hddsn - >> %s",s_Query);
			log_amx("[REGSYSTEM] Check_hddsn2 - >> %f",f_QueueTime);
			SQL_FreeHandle(h_Query);
			SQL_ThreadQuery(g_SQLTuple, "reg_user", s_Query,qData,1);
		}
}
public reg_user(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query failed.")
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
    else
	{    
		new hddsn[32];	
		get_user_info(s_Data[0], "*hwID", hddsn, 32);
		new s_Query[128];
		new qData[1];
		qData[0]=s_Data[0];			
		format(s_Query, charsmax(s_Query), "SELECT id FROM ibf_srr_hdd WHERE hddsn='%s'", hddsn);
		SQL_FreeHandle(h_Query);
		//log_amx("[REGSYSTEM] reg_user - >> %f",get_gametime()-g_Point);
		log_amx("[REGSYSTEM] reg_user - >> %f",f_QueueTime);
		SQL_ThreadQuery(g_SQLTuple, "Check_hddsn", s_Query,qData,1);
	}
		
}

/*
public get_id(i_FailState, Handle:h_Query, s_Error[], i_Errcode, s_Data[], i_DataSize, Float:f_QueueTime)
{
    if (i_FailState == TQUERY_CONNECT_FAILED)
        set_fail_state("Could not connect to database.")
    else if (i_FailState == TQUERY_QUERY_FAILED)
        set_fail_state("Query failed.")
    else if (i_Errcode)
        log_amx("Error on query: %s", s_Error)
    else
	{
	if (SQL_NumResults(h_Query))
	{				
		new regNumber[13];
		SQL_ReadResult(h_Query, 0, regNumber, charsmax(regNumber));
		console_print(s_Data[0],"Ваш регистрационный номер %s",regNumber);
	}else{log_amx("Error on query: get_id");}
	}
}
*/
public plugin_end() {
	SQL_FreeHandle(g_SQLTuple);
	//remove_task();
}

