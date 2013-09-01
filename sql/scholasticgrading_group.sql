--
-- Groups for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_group (
    sgg_id int unsigned NOT NULL AUTO_INCREMENT,
    sgg_title varchar(255) NOT NULL default '',
    sgg_enabled boolean NOT NULL default TRUE,
    PRIMARY KEY (sgg_id)
) /*$wgDBTableOptions*/;
