--
-- Assignments for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_assignment (
    sga_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
    sga_title varchar(255) NOT NULL default '',
    sga_value int unsigned NOT NULL default 0,
    sga_enabled boolean NOT NULL default TRUE,
    sga_date varbinary(14)
) /*$wgDBTableOptions*/;
