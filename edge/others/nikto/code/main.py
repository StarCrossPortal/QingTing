import json,base64,time,os
import subprocess,signal



# 执行nikto扫描
def nikto_scan(output_path,host):
    result_lst=[]
    cmd='nikto -host {}  -o /tmp/nikto.txt -F txt'.format(host)
    result=os.popen(cmd).read().strip()
    result=result.split('-----')
    while "" in result:
        result.remove("")
    with open(output_path,'w') as f:
        json.dump({"result":result[1]},f)


def main():
    input_path="/data/share/input_{}.json".format(os.environ["xflow_node_id"])
    output_path="/data/share/output_{}.json".format(os.environ["xflow_node_id"])
    with open(input_path,'r') as f:
        url_lst=json.load(f)
    for i in url_lst:
        host=i['host']
        nikto_scan(output_path,host)




if __name__ == "__main__":
    main()


















