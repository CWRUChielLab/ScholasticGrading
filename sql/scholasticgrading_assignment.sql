--
-- Assignments for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_assignment (
    sga_id int unsigned NOT NULL AUTO_INCREMENT,
    sga_title varchar(255) NOT NULL default '',
    sga_value decimal(8,4) NOT NULL default 0,
    sga_enabled boolean NOT NULL default TRUE,
    sga_date char(10) default NULL,
    PRIMARY KEY (sga_id)
) /*$wgDBTableOptions*/;
